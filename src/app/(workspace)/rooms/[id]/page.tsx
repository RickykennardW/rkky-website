import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Armchair, CalendarDays, ChevronLeft, ChevronRight, Grid2X2, MapPin, Table2, Users } from "lucide-react";
import { z } from "zod";
import { BookingForm } from "@/components/booking-form";
import { Breadcrumb, Notice, PageHeading, Status } from "@/components/ui";
import { getEquipment, getWorkspaceIdentity, getRooms } from "@/lib/data";
import { demoRooms } from "@/lib/demo";
import type { Search } from "@/lib/domain";
import { dateInput, formatDay, formatTime, nextDay, schedule, searchPeriod, toInstant, value } from "@/lib/time";

export const metadata = { title: "Room details" };
export default async function RoomDetail({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<Search> }) {
  const { id } = await params;
  if (!z.uuid().safeParse(id).success) notFound();
  const search = await searchParams;
  const { client, preview } = await getWorkspaceIdentity();
  const [rooms, equipment] = client ? await Promise.all([getRooms(client), getEquipment(client)]) : [demoRooms, []];
  const room = rooms.find((entry) => entry.id === id);
  if (!room) notFound();
  const period = searchPeriod(search);
  const date = value(search, "week") || period.date;
  const week = toInstant(date, "00:00") ? date : dateInput();
  const days = Array.from({ length: 7 }, (_, index) => nextDay(week, index));
  const weekStart = toInstant(week, "00:00")!;
  const weekEnd = toInstant(nextDay(week, 7), "00:00")!;
  let occupied: { starts_at: string; ends_at: string }[] = [];
  if (client) {
    const { data, error } = await client.rpc("room_schedule", { p_room: room.id, p_start: weekStart, p_end: weekEnd });
    if (error) throw new Error("The room schedule could not be loaded.");
    occupied = data;
  }
  const href = (week: string) => "/rooms/" + room.id + "?" + new URLSearchParams({ ...period, week }).toString();
  return <>
    <Breadcrumb href="/rooms" label="Campus rooms" current={room.name} />
    <PageHeading title={room.name} description={room.location} action={<Link href={"/planner?room=" + room.id} className="button button-secondary"><Grid2X2 className="size-4" /> Room planner</Link>} />
    <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
      <section className="panel overflow-hidden">
        {room.photo && <div className="relative aspect-[2.1] bg-raised"><Image src={"/rooms/" + room.photo + ".png"} alt={"Illustrative view of " + room.name} fill sizes="(min-width: 1280px) 55vw, 100vw" className="object-cover" /></div>}
        <div className="grid gap-4 p-5">
          <div className="flex flex-wrap items-center justify-between gap-2"><h2 className="section-title">Room information</h2>
            <Status status={preview ? "sample room" : room.active ? "check schedule" : "unavailable"} /></div>
          <p className="muted">{room.description}</p>
          <div className="flex flex-wrap gap-2">
            <span className="chip"><MapPin className="size-3.5" />{room.location}</span>
            <span className="chip"><Users className="size-3.5" />{room.capacity} people</span>
            <span className="chip"><Armchair className="size-3.5" />{room.chairs} chairs</span>
            <span className="chip"><Table2 className="size-3.5" />{room.tables_count} tables</span>
          </div>
          <div className="flex flex-wrap gap-2">{room.room_facilities.map(({ facility }) => <span className="chip" key={facility}>{facility}</span>)}</div>
        </div>
      </section>
      <section className="panel grid content-start gap-4 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="section-title flex items-center gap-2"><CalendarDays className="size-5 text-accent" /> Room calendar</h2>
          {!preview && <div className="flex gap-2">
            <Link href={href(nextDay(week, -7))} aria-label="Previous week" className="button button-ghost px-2"><ChevronLeft className="size-4" /></Link>
            <Link href={href(nextDay(week, 7))} aria-label="Next week" className="button button-ghost px-2"><ChevronRight className="size-4" /></Link>
          </div>}
        </div>
        {preview ? <Notice>Live calendar availability requires Supabase.</Notice> : <>
          <p className="text-xs text-muted">Approved reservation windows · Asia/Jakarta</p>
          <ul className="grid gap-2">{days.map((day) => {
            const start = toInstant(day, "00:00")!;
            const end = toInstant(nextDay(day), "00:00")!;
            const slots = occupied.filter((slot) => new Date(slot.starts_at) < new Date(end) && new Date(slot.ends_at) > new Date(start));
            return <li key={day} className="grid gap-1 rounded-lg border border-line bg-[#0e141c] px-3 py-2 text-xs sm:grid-cols-[100px_1fr]">
              <strong className="font-medium">{formatDay(start)}</strong>
              <span className="text-muted">{!room.active ? "Unavailable" : slots.length ? slots.map((slot) => (new Date(slot.starts_at) < new Date(start) ? "00:00" : formatTime(slot.starts_at)) + "–" + (new Date(slot.ends_at) >= new Date(end) ? "24:00" : formatTime(slot.ends_at))).join(", ") : "No approved reservations"}</span>
            </li>;
          })}</ul>
        </>}
      </section>
    </div>
    {preview ? <Notice>Connect Supabase to book this room. <Link href="/setup" className="underline">View setup steps</Link></Notice>
      : room.active ? <section className="grid gap-4" id="reservation">
        <h2 className="section-title">Request a reservation</h2>
        <BookingForm rooms={[room]} equipment={equipment} initialRoom={room.id} initialPeriod={schedule(period) ? period :
          { date: nextDay(dateInput()), endDate: nextDay(dateInput()), start: "09:00", end: "11:00" }} />
      </section> : <Notice>This room is temporarily unavailable for booking.</Notice>}
  </>;
}
