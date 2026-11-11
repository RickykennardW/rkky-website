import "server-only";
import { cache } from "react";
import { redirect, notFound } from "next/navigation";
import type { Availability, Booking, Equipment, Profile, Room } from "./domain";
import { supabaseSettings } from "./supabase-config";
import { createClient } from "./supabase";

export const getIdentity = cache(async () => {
  if (!supabaseSettings()) return { preview: true as const, profile: null, client: null };
  const client = await createClient();
  const { data } = await client.auth.getClaims();
  if (!data?.claims.sub) return { preview: false as const, profile: null, client };
  const { data: profile, error } = await client.from("profiles").select("id, full_name, email, role").eq("id", data.claims.sub).maybeSingle();
  if (error || !profile) throw new Error("Your campus profile could not be loaded. Apply the Supabase migration and try again.");
  return { preview: false as const, profile: profile as Profile, client };
});

export async function getWorkspaceIdentity() {
  const identity = await getIdentity();
  if (identity.preview) return identity;
  const { client, profile } = identity;
  if (!profile) redirect("/login");
  return { preview: false as const, client, profile };
}

export async function requireIdentity() {
  const identity = await getWorkspaceIdentity();
  if (identity.preview) redirect("/setup");
  return identity;
}

export async function requireAdmin() {
  const identity = await requireIdentity();
  if (identity.profile.role !== "admin") notFound();
  return identity;
}

type Client = Awaited<ReturnType<typeof createClient>>;

export async function getRooms(client: Client) {
  const { data, error } = await client.from("rooms").select("*, room_facilities(facility)").order("name");
  if (error) throw new Error("Rooms could not be loaded.");
  return data as Room[];
}

export async function getEquipment(client: Client) {
  const { data, error } = await client.from("equipment").select("*").order("name");
  if (error) throw new Error("Equipment could not be loaded.");
  return data as Equipment[];
}

const bookingSelect = "*, rooms(name,location), profiles!bookings_user_id_fkey(full_name,email), booking_equipment(equipment_id,quantity,equipment(name)), equipment_loans(equipment_id,quantity,picked_up_at,returned_at,missing,damaged,return_note,allocation_warning)";

export async function getBookings(client: Client, userId?: string) {
  const query = client.from("bookings").select(bookingSelect).order("starts_at", { ascending: false }).order("id");
  if (userId) query.eq("user_id", userId);
  const bookings: Booking[] = [];
  // Supabase's default row limit is 1,000 per request.
  for (let offset = 0; ; offset += 500) {
    const { data, error } = await query.range(offset, offset + 499);
    if (error) throw new Error("Bookings could not be loaded.");
    bookings.push(...data as unknown as Booking[]);
    if (data.length < 500) return bookings;
  }
}

export async function getBooking(client: Client, id: string, userId?: string) {
  const query = client.from("bookings").select(bookingSelect).eq("id", id);
  if (userId) query.eq("user_id", userId);
  const { data, error } = await query.maybeSingle();
  if (error) throw new Error("This booking could not be loaded.");
  if (!data) notFound();
  return data as unknown as Booking;
}

export async function getAvailability(client: Client, period: { startsAt: string; endsAt: string }) {
  const { data, error } = await client.rpc("resource_availability", { p_start: period.startsAt, p_end: period.endsAt });
  if (error) throw new Error("Availability could not be checked. Try again.");
  return data as Availability;
}
