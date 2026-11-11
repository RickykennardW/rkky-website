import { readFile } from "node:fs/promises";
import { PGlite } from "@electric-sql/pglite";
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, test } from "vitest";

const db = new PGlite();
const student = "b1000000-0000-4000-8000-000000000001";
const other = "b1000000-0000-4000-8000-000000000002";
const admin = "b1000000-0000-4000-8000-000000000003";
const room = "a1000000-0000-4000-8000-000000000001";
const equipment = "a2000000-0000-4000-8000-000000000003";
const start = "2090-05-10T09:00:00+07:00";
const end = "2090-05-10T11:00:00+07:00";
const load = (path: string) => readFile(new URL("../" + path, import.meta.url), "utf8");

async function asUser(id: string) {
  await db.exec("reset role");
  await db.query("select set_config('request.jwt.claim.sub',$1,true)", [id]);
  await db.exec("set local role authenticated");
}

// Use PostgreSQL with a small Auth schema fixture. The production migration,
// policies, constraints and RPCs run without edits in the test database.
beforeAll(async () => {
  await db.exec(`
    create role anon;
    create role authenticated;
    create schema auth;
    create table auth.users(id uuid primary key,email text,raw_user_meta_data jsonb);
    create function auth.uid() returns uuid language sql stable as $$
      select nullif(current_setting('request.jwt.claim.sub',true),'')::uuid;
    $$;
    grant usage on schema auth to authenticated;
  `);
  const migration = await load("supabase/migrations/20261111000100_campusspace.sql");
  try {
    await db.exec(migration);
  } catch (cause) {
    const { position } = cause as { position?: string };
    const offset = Number(position ?? 1) - 1;
    throw new Error("Migration failed near line " + migration.slice(0, offset).split("\n").length + ": " + (cause as Error).message);
  }
  await db.exec(await load("supabase/seed.sql"));
  await db.query(`insert into auth.users values
    ($1,'student@example.test','{"full_name":"Sam Student","role":"admin"}'),
    ($2,'other@example.test','{"full_name":"Taylor Student"}'),
    ($3,'admin@example.test','{"full_name":"Casey Admin"}')`, [student, other, admin]);
  await db.query("update public.profiles set role='admin' where id=$1", [admin]);
}, 30_000);

beforeEach(async () => { await db.exec("begin"); });
afterEach(async () => { await db.exec("rollback"); });
afterAll(async () => { await db.close(); });

async function request(options: { roomId?: string | null; count?: number; from?: string; to?: string; participants?: number } = {}) {
  const items = options.count ? [{ equipment_id: equipment, quantity: options.count }] : [];
  const { rows } = await db.query<{ id: string }>(`
    select public.create_booking($1,'Campus workshop','Peer learning workshop',$2,2,$3,$4,$5::jsonb,null) as id
  `, [options.roomId === undefined ? room : options.roomId, options.participants ?? 12, options.from ?? start, options.to ?? end, JSON.stringify(items)]);
  return rows[0].id;
}

async function approve(id: string) {
  await asUser(admin);
  await db.query("select public.review_booking($1,true)", [id]);
}

async function rejects(statement: () => Promise<unknown>, error: string | RegExp) {
  await db.exec("savepoint expected_failure");
  try { await expect(statement()).rejects.toThrow(error); }
  finally { await db.exec("rollback to savepoint expected_failure; release savepoint expected_failure"); }
}

async function getStatus(id: string) {
  return (await db.query<{ status: string }>("select status from public.bookings where id=$1", [id])).rows[0]?.status;
}

describe("Supabase data integrity", () => {
  test("new accounts get student profiles, even with admin metadata", async () => {
    await asUser(student);
    expect((await db.query("select full_name,role from public.profiles")).rows).toEqual([{ full_name: "Sam Student", role: "student" }]);
    await rejects(() => db.exec("update public.profiles set role='admin'"), /permission denied/);
    expect((await db.query("select name from public.rooms")).rows).toHaveLength(3);
  });

  test("anonymous users cannot read or book resources", async () => {
    await db.exec("set local role anon");
    await rejects(() => db.exec("select * from public.rooms"), /permission denied/);
    await rejects(() => db.exec("select * from public.equipment_loans(null::public.bookings)"), /permission denied/);
    await rejects(() => request(), /permission denied/);
  });

  test("students create pending bookings, cannot approve them or change other accounts' requests", async () => {
    await asUser(student);
    const id = await request();
    expect(await getStatus(id)).toBe("pending");
    await rejects(() => db.query("select public.review_booking($1,true)", [id]), "app_admin");
    await rejects(() => db.query("update public.bookings set status='approved' where id=$1", [id]), /permission denied/);
    await asUser(other);
    expect(await getStatus(id)).toBeUndefined();
    await rejects(() => db.query("select public.cancel_booking($1)", [id]), "app_state");
    await asUser(student);
    await db.query("select public.cancel_booking($1)", [id]);
    expect(await getStatus(id)).toBe("cancelled");
  });

  test("invalid date ranges, room capacity and an empty equipment-only request are rejected", async () => {
    await asUser(student);
    await rejects(() => request({ to: start }), "app_dates");
    await rejects(() => request({ from: "2000-01-01T10:00:00Z" }), "app_dates");
    await rejects(() => request({ participants: 80 }), "app_capacity");
    await rejects(() => request({ roomId: null }), "app_items");
    expect((await db.query("select id from public.bookings")).rows).toHaveLength(0);
  });

  test("only one overlapping room request can be approved; a back-to-back slot fits", async () => {
    await asUser(student);
    const first = await request();
    const conflicting = await request({ from: "2090-05-10T10:00:00+07:00", to: "2090-05-10T12:00:00+07:00" });
    const adjacent = await request({ from: end, to: "2090-05-10T12:00:00+07:00" });
    await approve(first);
    await rejects(() => approve(conflicting), "app_room_conflict");
    await approve(adjacent);
    expect(await getStatus(first)).toBe("approved");
    expect(await getStatus(conflicting)).toBe("pending");
    expect(await getStatus(adjacent)).toBe("approved");
    const { rows } = await db.query<{ slots: { rooms: Record<string, boolean> } }>("select public.resource_availability($1,$2) as slots", [start, end]);
    expect(rows[0].slots.rooms[room]).toBe(false);
  });

  test("approval rejects a pending plan after its room geometry changes", async () => {
    await asUser(student);
    const { rows } = await db.query<{ id: string }>(`
      select public.create_booking($1,'Planned workshop','Peer learning workshop',12,2,$2,$3,'[]',
        jsonb_build_object('roomLayout',layout,'participants',12,'committee',2,'chairs',14,'tables',4)) as id
      from public.rooms where id=$1
    `, [room, start, end]);
    await db.exec("reset role");
    await db.query("update public.rooms set layout = jsonb_set(layout,'{width}','15') where id=$1", [room]);
    await rejects(() => approve(rows[0].id), "app_plan_changed");
  });

  test("equipment approvals use peak overlap across the borrowing period", async () => {
    await asUser(student);
    const morning = await request({ roomId: null, count: 3, from: start, to: "2090-05-10T10:00:00+07:00" });
    const late = await request({ roomId: null, count: 3, from: "2090-05-10T10:00:00+07:00", to: end });
    const spanning = await request({ roomId: null, count: 2 });
    const excess = await request({ roomId: null, count: 1 });
    await approve(morning);
    await approve(late);
    await approve(spanning);
    await rejects(() => approve(excess), "app_equipment_conflict");
    expect(await getStatus(spanning)).toBe("approved");
    expect(await getStatus(excess)).toBe("pending");
    const { rows } = await db.query<{ slots: { equipment: Record<string, number> } }>("select public.resource_availability($1,$2) as slots", [start, end]);
    expect(rows[0].slots.equipment[equipment]).toBe(0);
  });

  test("inventory changes cannot reduce stock below outstanding approved allocations", async () => {
    await asUser(student);
    const id = await request({ roomId: null, count: 4 });
    await approve(id);
    await rejects(() => db.query("select public.save_equipment($1,'Portable Projector','Presentation','New description',3,0,'available')", [equipment]), "app_equipment_allocated");
    await rejects(() => db.query("select public.save_equipment($1,'Portable Projector','Presentation','New description',5,0,'maintenance')", [equipment]), "app_equipment_allocated");
    expect((await db.query<{ total_quantity: number }>("select total_quantity from public.equipment where id=$1", [equipment])).rows[0].total_quantity).toBe(5);
  });

  test("room closure or capacity reductions cannot invalidate approved reservations", async () => {
    await asUser(student);
    const id = await request();
    await approve(id);
    const update = (capacity: number, active: boolean) => db.query(`
      select public.save_room(id,name,location,description,$2,chairs,tables_count,$3,photo,layout,array['Projector','Air conditioning','Audio system','Wi-Fi'])
      from public.rooms where id=$1
    `, [room, capacity, active]);
    await rejects(() => update(80, false), "app_room_allocated");
    await rejects(() => update(10, true), "app_room_allocated");
    await update(80, true);
  });

  test("rejection records the review reason and reviewer", async () => {
    await asUser(student);
    const id = await request();
    await asUser(admin);
    await rejects(() => db.query("select public.review_booking($1,false,' ')", [id]), "app_reason");
    await db.query("select public.review_booking($1,false,'Room is needed for examinations.')", [id]);
    expect((await db.query("select status,review_note,reviewed_by from public.bookings where id=$1", [id])).rows[0]).toEqual({
      status: "rejected", review_note: "Room is needed for examinations.", reviewed_by: admin,
    });
  });

  test("pickup and return are audited, validated and update usable stock atomically", async () => {
    await asUser(student);
    const future = await request({ roomId: null, count: 5 });
    await approve(future);
    await db.exec("reset role");
    const { rows } = await db.query<{ id: string }>(`
      insert into public.bookings(user_id,title,description,participants,committee,starts_at,ends_at,status)
      values($1,'Current loan','Equipment handoff',1,0,now()-interval '30 minutes',now()+interval '30 minutes','approved')
      returning id
    `, [student]);
    const id = rows[0].id;
    await db.query("insert into public.booking_equipment values($1,$2,3)", [id, equipment]);
    await asUser(student);
    await rejects(() => db.query("select public.record_pickup($1)", [id]), "app_admin");
    await asUser(admin);
    await rejects(() => db.query("select public.record_pickup($1)", [future]), "app_pickup_time");
    await db.query("select public.record_pickup($1)", [id]);
    await rejects(() => db.query("select public.record_pickup($1)", [id]), "app_state");
    const report = (missing: number, damaged: number) => db.query<{ warning: boolean }>(
      "select public.record_return($1,$2::jsonb,'Condition checked') as warning",
      [id, JSON.stringify([{ equipment_id: equipment, missing, damaged }])],
    );
    await rejects(() => report(2, 2), "app_return_counts");
    expect((await report(1, 1)).rows[0].warning).toBe(true);
    expect(await getStatus(id)).toBe("completed");
    expect((await db.query(`
      select loan.picked_up_by, loan.returned_by, loan.missing, loan.damaged, loan.allocation_warning
      from public.bookings b
      cross join lateral public.equipment_loans(b) loan
      where b.id=$1
    `, [id])).rows[0])
      .toEqual({ picked_up_by: admin, returned_by: admin, missing: 1, damaged: 1, allocation_warning: true });
    expect((await db.query("select unusable_quantity from public.equipment where id=$1", [equipment])).rows[0]).toEqual({ unusable_quantity: 2 });
    await rejects(() => report(1, 1), "app_state");

    const embeddedLoans = () => db.query(`
      select equipment_id, quantity
      from public.equipment_loans(jsonb_populate_record(null::public.bookings, jsonb_build_object('id', $1::uuid)))
    `, [id]);
    await asUser(student);
    expect((await embeddedLoans()).rows).toEqual([{ equipment_id: equipment, quantity: 3 }]);
    await asUser(other);
    expect((await embeddedLoans()).rows).toEqual([]);
  });
});
