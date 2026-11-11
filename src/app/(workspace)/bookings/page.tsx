import Link from "next/link";
import { CalendarPlus } from "lucide-react";
import { BookingList } from "@/components/booking-list";
import { BookingFilters } from "@/components/booking-filters";
import { PageHeading } from "@/components/ui";
import { getBookings, requireIdentity } from "@/lib/data";
import { bookingStatuses, type BookingStatus, type Search } from "@/lib/domain";
import { value } from "@/lib/time";

export const metadata = { title: "My bookings" };
export default async function MyBookings({ searchParams }: { searchParams: Promise<Search> }) {
  const { client, profile } = await requireIdentity();
  const selection = value(await searchParams, "status") as BookingStatus;
  const status = bookingStatuses.includes(selection) ? selection : "all";
  const bookings = await getBookings(client, profile.id);
  return <>
    <PageHeading title="My bookings" description="Track requests, reservations, and equipment borrowing history." action={<Link href="/book" className="button button-secondary"><CalendarPlus className="size-4" /> Request resources</Link>} />
    <BookingFilters current={status} path="/bookings" />
    <BookingList bookings={bookings.filter((booking) => status === "all" || booking.status === status)} />
  </>;
}
