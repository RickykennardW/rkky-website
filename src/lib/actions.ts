"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import type { ActionState, Room } from "./domain";
import { requireAdmin, requireIdentity } from "./data";
import { createClient } from "./supabase";
import { supabaseSettings } from "./supabase-config";
import { bookingSchema, equipmentSchema, loginSchema, returnSchema, roomSchema, signupSchema } from "./validation";
import { generatePlan, planSchema, type Plan } from "./planner";
import { schedule } from "./time";

const messages: Record<string, string> = {
  app_auth: "Sign in to continue.",
  app_admin: "Campus administrator access is required.",
  app_dates: "Choose a future start and an end after it.",
  app_state: "This action is no longer available for that request. Refresh and try again.",
  app_room_unavailable: "That room is temporarily unavailable.",
  app_capacity: "Participants and committee members exceed the room’s capacity.",
  app_room_conflict: "The room is already reserved during this schedule.",
  app_equipment_conflict: "There are insufficient usable equipment units for this schedule.",
  app_items: "Choose a room or add at least one equipment item.",
  app_plan: "The room plan does not match the booking.",
  app_plan_changed: "The room geometry or furniture changed. Submit an updated plan before approval.",
  app_reason: "Enter a rejection reason between 3 and 1,000 characters.",
  app_room_allocated: "This change would affect an approved room reservation or room plan.",
  app_equipment_allocated: "Approved bookings or active loans still require this stock.",
  app_pickup_time: "Pickup can be recorded during the approved reservation window.",
  app_return_counts: "Enter missing and damaged quantities for each loan. Their sum cannot exceed the borrowed quantity.",
  "23505": "That name or selection is already in use.",
  "23503": "A selected resource no longer exists. Refresh and try again.",
  "23514": "One or more values are outside the permitted range.",
};
const err = (message: string): ActionState => ({ error: message });
const field = (form: FormData, name: string) => form.get(name)?.toString() ?? "";
const json = (input: string): unknown => { try { return JSON.parse(input); } catch { return undefined; } };
const firstIssue = (issue: z.ZodError) => issue.issues[0]?.message ?? "Review the submitted fields.";
const dbError = (error: { message: string; code: string }) => err(messages[error.message] ?? messages[error.code] ?? "The operation could not be completed. Please try again.");
const uuid = z.uuid();

export async function login(_state: ActionState, form: FormData): Promise<ActionState> {
  if (!supabaseSettings()) return err("Connect Supabase before signing in.");
  const parsed = loginSchema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return err("Enter a valid email address and password.");
  const client = await createClient();
  const { error } = await client.auth.signInWithPassword(parsed.data);
  if (error) return err(error.code === "email_not_confirmed" ? "Confirm your email first, then sign in." : "Sign-in failed. Check your credentials and try again.");
  redirect("/");
}

export async function signup(_state: ActionState, form: FormData): Promise<ActionState> {
  if (!supabaseSettings()) return err("Connect Supabase before creating an account.");
  const parsed = signupSchema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return err(firstIssue(parsed.error));
  const client = await createClient();
  const site = process.env.NEXT_PUBLIC_SITE_URL || (process.env.VERCEL_URL ? "https://" + process.env.VERCEL_URL : "http://localhost:3000");
  const { data, error } = await client.auth.signUp({
    email: parsed.data.email,
    password: parsed.data.password,
    options: { data: { full_name: parsed.data.fullName }, emailRedirectTo: new URL("/auth/confirm", site).toString() },
  });
  if (error) return err(error.code === "over_email_send_rate_limit" ? "Too many emails requested. Try again later." : "The account could not be created. Check your details and try again.");
  if (data.session) redirect("/");
  return { success: "Check your inbox for an email to confirm your account, then sign in." };
}

export async function logout(): Promise<void> {
  const { client } = await requireIdentity();
  const { error } = await client.auth.signOut({ scope: "local" });
  if (error) redirect("/?logoutError=1");
  redirect("/login");
}

export async function createBooking(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireIdentity();
  const parsed = bookingSchema.safeParse({ ...Object.fromEntries(form), items: json(field(form, "items")) });
  if (!parsed.success) return err(firstIssue(parsed.error));
  const booking = parsed.data;
  const period = schedule(booking, true);
  if (!period) return err(messages.app_dates);
  let plan: (Plan & { roomLayout: Room["layout"] }) | null = null;
  const serialized = field(form, "planner");
  if (serialized) {
    const configuration = planSchema.safeParse(json(serialized));
    if (!booking.roomId || !configuration.success || configuration.data.participants !== booking.participants || configuration.data.committee !== booking.committee) {
      return err("Update the room plan to match the booking’s attendance.");
    }
    const { data: room, error } = await client.from("rooms").select("*, room_facilities(facility)").eq("id", booking.roomId).maybeSingle();
    if (error || !room) return err("The selected room could not be loaded.");
    const layout = generatePlan(room as Room, configuration.data);
    if (layout.error) return err(layout.error);
    plan = { ...configuration.data, roomLayout: (room as Room).layout };
  }
  const { data, error } = await client.rpc("create_booking", {
    p_room: booking.roomId ?? null, p_title: booking.title, p_description: booking.description,
    p_participants: booking.participants, p_committee: booking.committee, p_start: period.startsAt, p_end: period.endsAt,
    p_items: booking.items, p_planner: plan,
  });
  if (error) return dbError(error);
  revalidatePath("/");
  redirect("/bookings/" + data + "?created=1");
}

export async function cancelBooking(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireIdentity();
  const id = uuid.safeParse(field(form, "booking"));
  if (!id.success) return err("Invalid booking.");
  const { error } = await client.rpc("cancel_booking", { p_booking: id.data });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/bookings/" + id.data + "?result=cancelled");
}

export async function reviewBooking(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const id = uuid.safeParse(field(form, "booking"));
  const decision = field(form, "decision");
  if (!id.success || !["approve", "reject"].includes(decision)) return err("Choose a valid booking decision.");
  const reason = field(form, "reason").trim();
  if (decision === "reject" && (reason.length < 3 || reason.length > 1000)) return err(messages.app_reason);
  const { error } = await client.rpc("review_booking", { p_booking: id.data, p_approve: decision === "approve", p_reason: reason || null });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/bookings/" + id.data + "?result=" + (decision === "approve" ? "approved" : "rejected"));
}

export async function recordPickup(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const id = uuid.safeParse(field(form, "booking"));
  if (!id.success) return err("Invalid booking.");
  const { error } = await client.rpc("record_pickup", { p_booking: id.data });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/bookings/" + id.data + "?result=pickup");
}

export async function recordReturn(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const id = uuid.safeParse(field(form, "booking"));
  const items = form.getAll("equipment_id").map((id) => ({
    equipment_id: id, missing: field(form, "missing:" + id), damaged: field(form, "damaged:" + id),
  }));
  const payload = returnSchema.safeParse({ items, note: field(form, "note") });
  if (!id.success || !payload.success) return err(messages.app_return_counts);
  const { data, error } = await client.rpc("record_return", { p_booking: id.data, p_items: payload.data.items, p_note: payload.data.note });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/bookings/" + id.data + "?result=" + (data ? "shortage" : "returned"));
}

export async function completeRoom(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const id = uuid.safeParse(field(form, "booking"));
  if (!id.success) return err("Invalid booking.");
  const { error } = await client.rpc("complete_room_booking", { p_booking: id.data });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/bookings/" + id.data + "?result=complete");
}

export async function saveRoom(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const parsed = roomSchema.safeParse({
    ...Object.fromEntries(form), active: form.has("active"), facilities: form.getAll("facilities"),
    layout: {
      width: Number(field(form, "width")), depth: Number(field(form, "depth")),
      door: { wall: field(form, "doorWall"), offset: Number(field(form, "doorOffset")), width: Number(field(form, "doorWidth")), clearance: Number(field(form, "doorClearance")) },
      blocked: json(field(form, "blocked")), fixed: json(field(form, "fixed")),
    },
  });
  if (!parsed.success) return err(firstIssue(parsed.error));
  const room = parsed.data;
  const { error } = await client.rpc("save_room", {
    p_id: room.id ?? null, p_name: room.name, p_location: room.location, p_description: room.description,
    p_capacity: room.capacity, p_chairs: room.chairs, p_tables: room.tables, p_active: room.active,
    p_photo: room.photo || null, p_layout: room.layout, p_facilities: room.facilities,
  });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/rooms?saved=1");
}

export async function saveEquipment(_state: ActionState, form: FormData): Promise<ActionState> {
  const { client } = await requireAdmin();
  const parsed = equipmentSchema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return err(firstIssue(parsed.error));
  const item = parsed.data;
  const { error } = await client.rpc("save_equipment", {
    p_id: item.id ?? null, p_name: item.name, p_category: item.category, p_description: item.description,
    p_total: item.total, p_unusable: item.unusable, p_status: item.status,
  });
  if (error) return dbError(error);
  revalidatePath("/", "layout");
  redirect("/admin/equipment?saved=1");
}
