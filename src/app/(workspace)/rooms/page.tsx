import Link from "next/link";
import { Building2 } from "lucide-react";
import { getAvailability, getWorkspaceIdentity, getRooms } from "@/lib/data";
import { demoRooms } from "@/lib/demo";
import { facilities, type Search } from "@/lib/domain";
import { periodQuery, schedule, searchPeriod, value } from "@/lib/time";
import { CatalogFilter, RoomCard } from "@/components/catalog";
import { Empty, Notice, PageHeading } from "@/components/ui";

export const metadata = { title: "Rooms" };
export default async function Rooms({ searchParams }: { searchParams: Promise<Search> }) {
  const search = await searchParams;
  const { client, preview } = await getWorkspaceIdentity();
  const period = searchPeriod(search);
  const range = schedule(period);
  const capacity = Math.max(0, Number(value(search, "capacity")) || 0);
  const selected = typeof search.facility === "string" ? [search.facility] : search.facility ?? [];
  const chosen = facilities.filter((facility) => selected.includes(facility));
  const [rooms, availability] = await Promise.all([client ? getRooms(client) : demoRooms, client && range ? getAvailability(client, range) : null]);
  const filtered = rooms.filter((room) => room.capacity >= capacity && chosen.every((facility) => room.room_facilities.some((entry) => entry.facility === facility))
    && (!availability || availability.rooms[room.id]));
  return <>
    <PageHeading title="Campus rooms" description="Find a room by capacity, facilities, or reservation period." />
    {preview && <Notice>Fictional room catalog. <Link href="/setup" className="underline">Connect Supabase</Link> for live calendars and booking.</Notice>}
    <CatalogFilter period={period} preview={preview} clear="/rooms">
      <label className="label w-38">Minimum capacity<input className="input" type="number" min="1" max="500" name="capacity" placeholder="Any" defaultValue={capacity || ""} /></label>
      <fieldset className="grid min-w-0 gap-2"><legend className="mb-2 text-sm font-medium">Facilities</legend>
        <div className="flex flex-wrap gap-2">{facilities.map((facility) => <label className="chip min-h-9 cursor-pointer" key={facility}><input className="checkbox" type="checkbox" name="facility" value={facility} defaultChecked={chosen.includes(facility)} />{facility}</label>)}</div>
      </fieldset>
    </CatalogFilter>
    {!preview && Object.values(period).some(Boolean) && !range && <Notice tone="error">Enter both dates and times, with the end after the start.</Notice>}
    <div className="flex items-center justify-between gap-2"><h2 className="section-title">{filtered.length} {filtered.length === 1 ? "room" : "rooms"}</h2>{availability && <span className="text-xs text-muted">Available for the selected period</span>}</div>
    {filtered.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{filtered.map((room, index) => <RoomCard key={room.id} room={room} available={availability?.rooms[room.id]} query={range ? periodQuery(period) : undefined} preview={preview} eager={index < 3} />)}</div>
      : <Empty icon={Building2} title="No rooms match these filters" description="Try a different time, lower capacity, or fewer facilities." href="/rooms" action="Clear filters" />}
  </>;
}
