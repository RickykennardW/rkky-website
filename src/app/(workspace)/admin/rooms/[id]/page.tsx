import { notFound } from "next/navigation";
import { z } from "zod";
import { RoomEditor } from "@/components/resource-editor";
import { Breadcrumb, PageHeading } from "@/components/ui";
import { getRooms, requireAdmin } from "@/lib/data";

export default async function EditRoom({ params }: { params: Promise<{ id: string }> }) {
  const { client } = await requireAdmin();
  const { id } = await params;
  if (id !== "new" && !z.uuid().safeParse(id).success) notFound();
  const room = id === "new" ? undefined : (await getRooms(client)).find((entry) => entry.id === id);
  if (id !== "new" && !room) notFound();
  return <>
    <Breadcrumb href="/admin/rooms" label="Manage rooms" current={room?.name ?? "New room"} />
    <PageHeading title={room ? "Edit " + room.name : "Add room"} />
    <RoomEditor room={room} />
  </>;
}
