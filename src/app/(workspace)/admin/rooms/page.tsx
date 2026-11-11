import Link from "next/link";
import { Building2, Pencil, Plus } from "lucide-react";
import { Empty, Notice, PageHeading, Status } from "@/components/ui";
import { getRooms, requireAdmin } from "@/lib/data";
import type { Search } from "@/lib/domain";

export const metadata = { title: "Manage rooms" };
export default async function ManageRooms({ searchParams }: { searchParams: Promise<Search> }) {
  const { client } = await requireAdmin();
  const rooms = await getRooms(client);
  return <>
    <PageHeading title="Manage rooms" description="Configure capacity, facilities, availability, and room geometry." action={<Link href="/admin/rooms/new" className="button button-primary"><Plus className="size-4" /> Add room</Link>} />
    {(await searchParams).saved === "1" && <Notice tone="success">Room saved.</Notice>}
    {rooms.length ? <ul className="panel divide-y divide-line">{rooms.map((room) => <li key={room.id} className="flex flex-wrap items-center gap-3 px-4 py-4 sm:px-5">
      <div className="min-w-0 flex-1"><p className="font-semibold">{room.name}</p><p className="mt-1 text-xs text-muted">{room.location} · {room.capacity} people · {room.chairs} chairs · {room.tables_count} tables</p></div>
      <Status status={room.active ? "available" : "unavailable"} />
      <Link href={"/admin/rooms/" + room.id} className="button button-ghost"><Pencil className="size-4" /> Edit<span className="sr-only"> {room.name}</span></Link>
    </li>)}</ul> : <Empty icon={Building2} title="No rooms yet" description="Add the first room to the campus catalog." href="/admin/rooms/new" action="Add room" />}
  </>;
}
