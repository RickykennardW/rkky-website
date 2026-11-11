import Link from "next/link";
import { ArrowRight, Building2, CalendarCheck, Clock3, PackageCheck } from "lucide-react";
import { BookingList } from "@/components/booking-list";
import { Notice, PageHeading, Stat } from "@/components/ui";
import { getBookings, getEquipment, getRooms, requireAdmin } from "@/lib/data";

export const metadata = { title: "Administration" };
export default async function AdminDashboard() {
  const { client } = await requireAdmin();
  const [bookings, rooms, equipment] = await Promise.all([getBookings(client), getRooms(client), getEquipment(client)]);
  const pending = bookings.filter((booking) => booking.status === "pending");
  const approved = bookings.filter((booking) => booking.status === "approved");
  const activeLoans = bookings.flatMap((booking) => booking.equipment_loans).filter((loan) => !loan.returned_at);
  const upcoming = bookings.filter((booking) => booking.rooms && ["approved", "completed"].includes(booking.status) && new Date(booking.ends_at) > new Date())
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
  const losses = bookings.filter((booking) => booking.equipment_loans.some((loan) => loan.allocation_warning));
  const usable = equipment.reduce((total, item) => total + (item.status === "available" ? item.total_quantity - item.unusable_quantity : 0), 0);
  return <>
    <PageHeading title="Admin overview" description="Review requests, track room usage, and manage campus inventory." action={<Link href="/admin/bookings?status=pending" className="button button-primary">Review requests<ArrowRight className="size-4" /></Link>} />
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Stat icon={Clock3} label="Pending requests" count={pending.length} detail="Awaiting a decision" />
      <Stat icon={CalendarCheck} label="Approved reservations" count={approved.length} detail="Current approved requests" />
      <Stat icon={PackageCheck} label="Active equipment loans" count={activeLoans.length} detail="Item lines awaiting return" />
      <Stat icon={Building2} label="Rooms in service" count={rooms.filter((room) => room.active).length} detail={rooms.length + " rooms in the catalog"} />
    </div>
    {losses.length > 0 && <Notice>{losses.length} return {losses.length === 1 ? "record flagged" : "records flagged"} a stock loss affecting approved allocations. <Link href="/admin/equipment" className="underline">Review inventory</Link>.</Notice>}
    <section className="panel flex flex-wrap items-center justify-between gap-3 p-5">
      <div><h2 className="section-title">Inventory summary</h2><p className="muted mt-1">{equipment.length} equipment types · {usable} usable units across available items.</p></div>
      <Link href="/admin/equipment" className="button button-secondary">Manage inventory <ArrowRight className="size-4" /></Link>
    </section>
    <div className="grid gap-5 xl:grid-cols-2">
      <section className="grid content-start gap-3"><h2 className="section-title">Pending review</h2><BookingList bookings={pending.slice(0, 5)} admin empty="No pending requests" /></section>
      <section className="grid content-start gap-3"><h2 className="section-title">Upcoming room usage</h2><BookingList bookings={upcoming.slice(0, 5)} admin empty="No upcoming room usage" /></section>
    </div>
  </>;
}
