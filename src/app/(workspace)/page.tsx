import Image from "next/image";
import Link from "next/link";
import { ArrowRight, Boxes, CalendarCheck, CalendarDays, Clock3 } from "lucide-react";
import { getBookings, getWorkspaceIdentity, getRooms } from "@/lib/data";
import { demoRooms } from "@/lib/demo";
import { Notice, PageHeading, Stat } from "@/components/ui";
import { RoomCard } from "@/components/catalog";
import { BookingList } from "@/components/booking-list";

export default async function Dashboard({ searchParams }: { searchParams: Promise<{ logoutError?: string }> }) {
  const { client, profile, preview } = await getWorkspaceIdentity();
  const [rooms, bookings] = preview ? [demoRooms, []] : await Promise.all([getRooms(client), getBookings(client, profile.id)]);
  const featuredRooms = rooms.filter((room) => room.active).slice(0, 3);
  const upcoming = bookings.filter((booking) => booking.status === "approved" && new Date(booking.ends_at) > new Date())
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
  return <>
    <PageHeading title="Dashboard" description={profile ? "Welcome back, " + profile.full_name.split(" ")[0] + ". Manage your campus reservations here." : "Campus rooms, equipment, and reservations in one place."} />
    {(await searchParams).logoutError && <Notice tone="error">Sign-out failed. Please try again.</Notice>}
    {preview && <Notice><span className="font-semibold">Catalog preview.</span> These are fictional resources. <Link className="font-semibold underline underline-offset-4" href="/setup">Connect Supabase</Link> to sign in and make bookings.</Notice>}
    <section className="relative isolate flex min-h-[254px] items-center overflow-hidden rounded-xl border border-[#31445d] bg-[#13243a] sm:min-h-[294px]">
      <Image src="/rooms/seminar.png" alt="" fill priority sizes="(min-width: 1024px) 82vw, 100vw" className="object-cover object-[center_57%]" />
      <div aria-hidden="true" className="absolute inset-0 bg-linear-to-r from-[#0a172a] via-[#0b192a]/90 to-[#0b1726]/25" />
      <div className="relative grid max-w-[530px] gap-4 px-6 py-8 sm:px-9 sm:py-10">
        <h2 className="text-[clamp(1.9rem,4vw,3.2rem)] leading-[1.07] font-semibold tracking-[-0.055em] text-white">Book a campus room</h2>
        <p className="max-w-md text-sm leading-relaxed text-[#c9d7e8] sm:text-base">Find a room for your next event, then add the equipment you need.</p>
        <div className="mt-2 flex flex-wrap gap-2.5">
          <Link href="/rooms" className="button button-primary">Explore rooms <ArrowRight className="size-4" /></Link>
          <Link href="/equipment" className="button button-secondary border-[#46607d] bg-[#152840]"><Boxes className="size-4" /> Equipment</Link>
        </div>
      </div>
    </section>
    <section aria-label="Reservation summary" className="grid gap-3 sm:grid-cols-3 sm:gap-4">
      <Stat icon={CalendarDays} label="Upcoming reservations" count={preview ? "—" : upcoming.length} detail="Approved and still ahead" />
      <Stat icon={Clock3} label="Pending requests" count={preview ? "—" : bookings.filter((booking) => booking.status === "pending").length} detail="Waiting for campus review" />
      <Stat icon={CalendarCheck} label="Approved bookings" count={preview ? "—" : bookings.filter((booking) => booking.status === "approved").length} detail="Confirmed by an administrator" />
    </section>
    <section className="grid gap-4" aria-labelledby="dashboard-rooms">
      <div className="flex flex-wrap items-end justify-between gap-2">
        <div><h2 id="dashboard-rooms" className="section-title">{preview ? "Sample rooms" : "Campus rooms"}</h2><p className="muted mt-1">Compare room sizes and facilities.</p></div>
        <Link href="/rooms" className="flex min-h-9 items-center gap-2 text-sm font-semibold text-accent hover:text-ink">View all rooms <ArrowRight className="size-4" /></Link>
      </div>
      {featuredRooms.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{featuredRooms.map((room) => <RoomCard key={room.id} room={room} preview={preview} />)}</div>
        : <p className="muted panel p-5">The campus catalog has no rooms yet.</p>}
    </section>
    {!preview && <section className="grid gap-4"><h2 className="section-title">Upcoming reservations</h2><BookingList bookings={upcoming.slice(0, 5)} empty="No upcoming reservations" /></section>}
  </>;
}
