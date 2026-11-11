import { notFound } from "next/navigation";
import { z } from "zod";
import { BookingDetail } from "@/components/booking-detail";
import { AdminControls } from "@/components/booking-controls";
import { Breadcrumb, Notice, PageHeading } from "@/components/ui";
import { getBooking, requireAdmin } from "@/lib/data";
import type { Search } from "@/lib/domain";
import { value } from "@/lib/time";

export const metadata = { title: "Review booking" };
const feedback: Record<string, string> = {
  approved: "Booking approved and resources allocated.", rejected: "Booking rejected.", pickup: "Equipment pickup recorded.",
  returned: "Equipment return recorded.", shortage: "Return recorded. Stock losses affect approved allocations. Review inventory.",
  complete: "Room booking marked complete.",
};
export default async function AdminBooking({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<Search> }) {
  const { client } = await requireAdmin();
  const { id } = await params;
  if (!z.uuid().safeParse(id).success) notFound();
  const booking = await getBooking(client, id);
  const result = value(await searchParams, "result");
  const now = new Date();
  const ended = now >= new Date(booking.ends_at);
  return <>
    <Breadcrumb href="/admin/bookings" label="Booking requests" current="Review" />
    <PageHeading title={booking.title} />
    {feedback[result] && <Notice tone={result === "shortage" ? "info" : "success"}>{feedback[result]}</Notice>}
    <BookingDetail booking={booking} admin />
    <AdminControls booking={booking} pickupOpen={!ended && now >= new Date(booking.starts_at)} ended={ended} />
  </>;
}
