import { BookingList } from "@/components/booking-list";
import { BookingFilters } from "@/components/booking-filters";
import { PageHeading } from "@/components/ui";
import { getBookings, requireAdmin } from "@/lib/data";
import { bookingStatuses, type BookingStatus, type Search } from "@/lib/domain";
import { value } from "@/lib/time";

export const metadata = { title: "Booking requests" };
export default async function AdminBookings({ searchParams }: { searchParams: Promise<Search> }) {
  const { client } = await requireAdmin();
  const selection = value(await searchParams, "status") as BookingStatus;
  const status = bookingStatuses.includes(selection) ? selection : "all";
  const bookings = await getBookings(client);
  return <>
    <PageHeading title="Booking requests" description="Review event details, allocate resources, and record equipment handoffs." />
    <BookingFilters current={status} path="/admin/bookings" />
    <BookingList bookings={bookings.filter((booking) => status === "all" || booking.status === status)} admin />
  </>;
}
