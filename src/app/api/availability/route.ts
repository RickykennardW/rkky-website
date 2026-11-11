import { NextResponse, type NextRequest } from "next/server";
import { getAvailability, getIdentity } from "@/lib/data";
import { schedule, searchPeriod } from "@/lib/time";

export async function GET(request: NextRequest) {
  const { client, profile, preview } = await getIdentity();
  if (preview) return NextResponse.json({ error: "Connect Supabase to check live availability." }, { status: 503 });
  if (!profile || !client) return NextResponse.json({ error: "Sign in to check availability." }, { status: 401 });
  const range = schedule(searchPeriod(Object.fromEntries(request.nextUrl.searchParams)));
  if (!range) return NextResponse.json({ error: "Enter a valid reservation period." }, { status: 400 });
  try {
    return NextResponse.json(await getAvailability(client, range), { headers: { "Cache-Control": "private, no-store" } });
  } catch {
    return NextResponse.json({ error: "Availability could not be checked. Try again." }, { status: 503 });
  }
}
