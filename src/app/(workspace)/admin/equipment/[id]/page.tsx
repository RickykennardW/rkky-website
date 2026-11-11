import { notFound } from "next/navigation";
import { z } from "zod";
import { EquipmentEditor } from "@/components/resource-editor";
import { Breadcrumb, PageHeading } from "@/components/ui";
import { getEquipment, requireAdmin } from "@/lib/data";

export default async function EditEquipment({ params }: { params: Promise<{ id: string }> }) {
  const { client } = await requireAdmin();
  const { id } = await params;
  if (id !== "new" && !z.uuid().safeParse(id).success) notFound();
  const item = id === "new" ? undefined : (await getEquipment(client)).find((entry) => entry.id === id);
  if (id !== "new" && !item) notFound();
  return <>
    <Breadcrumb href="/admin/equipment" label="Manage equipment" current={item?.name ?? "New equipment"} />
    <PageHeading title={item ? "Edit " + item.name : "Add equipment"} />
    <EquipmentEditor item={item} />
  </>;
}
