import { PlannerDirectory } from "@/components/planner";
import { Notice, PageHeading } from "@/components/ui";
import { getWorkspaceIdentity, getRooms } from "@/lib/data";
import { demoRooms } from "@/lib/demo";
import type { Search } from "@/lib/domain";
import { value } from "@/lib/time";

export const metadata = { title: "Room Planner" };
export default async function RoomPlanner({ searchParams }: { searchParams: Promise<Search> }) {
  const { client, preview } = await getWorkspaceIdentity();
  const rooms = (client ? await getRooms(client) : demoRooms).filter((room) => room.active);
  return <>
    <PageHeading title="Smart Room Planner" description="Estimate equipment and preview a setup against a room’s geometry and furniture." />
    {preview && <Notice>You’re planning with fictional room dimensions and furniture. Connect Supabase to save a plan with a booking.</Notice>}
    <PlannerDirectory rooms={rooms} selected={value(await searchParams, "room")} allowBooking={!preview} />
  </>;
}
