import { BookingForm } from "@/components/booking-form";
import { PageHeading } from "@/components/ui";
import { getEquipment, getRooms, requireIdentity } from "@/lib/data";
import type { Search } from "@/lib/domain";
import { planSchema } from "@/lib/planner";
import { dateInput, nextDay, searchPeriod, value } from "@/lib/time";

export const metadata = { title: "New booking" };
export default async function Book({ searchParams }: { searchParams: Promise<Search> }) {
  const { client } = await requireIdentity();
  const search = await searchParams;
  const [rooms, equipment] = await Promise.all([getRooms(client), getEquipment(client)]);
  const initialRoom = rooms.find((room) => room.active && room.id === value(search, "room"));
  const initialEquipment = equipment.find((item) => item.status === "available" && item.id === value(search, "equipment"));
  const date = nextDay(dateInput());
  const period = searchPeriod(search);
  let plan;
  try { plan = initialRoom ? planSchema.parse(JSON.parse(value(search, "plan"))) : undefined; } catch { plan = undefined; }
  return <>
    <PageHeading title="New booking" description="Request a room, equipment, or both. A campus administrator reviews each request." />
    <BookingForm rooms={rooms} equipment={equipment} initialRoom={initialRoom?.id} initialEquipment={initialEquipment?.id}
      initialPeriod={period.date ? period : { date, endDate: date, start: "09:00", end: "11:00" }} initialPlan={plan} />
  </>;
}
