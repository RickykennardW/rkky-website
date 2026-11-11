import { notFound } from "next/navigation";
import { z } from "zod";
import { BookingDetail } from "@/components/booking-detail";
import { StudentControls } from "@/components/booking-controls";
import { Breadcrumb, Notice, PageHeading } from "@/components/ui";
import { getBooking, requireIdentity } from "@/lib/data";
import type { Search } from "@/lib/domain";

export const metadata = { title: "Booking details" };
export default async function StudentBooking({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<Search> }) {
  const { client, profile } = await requireIdentity();
  const { id } = await params;
  if (!z.uuid().safeParse(id).success) notFound();
  const booking = await getBooking(client, id, profile.id);
  const search = await searchParams;
  return <>
    <Breadcrumb href="/bookings" label="My bookings" current="Booking details" />
    <PageHeading title={booking.title} />
    {search.created === "1" && <Notice tone="success">Request submitted. A campus administrator will review it.</Notice>}
    {search.result === "cancelled" && <Notice tone="success">Booking cancelled.</Notice>}
    <BookingDetail booking={booking} />
    <StudentControls booking={booking} />
  </>;
}
