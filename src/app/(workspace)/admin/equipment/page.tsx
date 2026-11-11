import Link from "next/link";
import { Package, Pencil, Plus } from "lucide-react";
import { Empty, Notice, PageHeading, Status } from "@/components/ui";
import { getEquipment, requireAdmin } from "@/lib/data";
import type { Search } from "@/lib/domain";

export const metadata = { title: "Manage equipment" };
export default async function ManageEquipment({ searchParams }: { searchParams: Promise<Search> }) {
  const { client } = await requireAdmin();
  const items = await getEquipment(client);
  return <>
    <PageHeading title="Manage equipment" description="Maintain stock, descriptions, and maintenance status." action={<Link href="/admin/equipment/new" className="button button-primary"><Plus className="size-4" /> Add equipment</Link>} />
    {(await searchParams).saved === "1" && <Notice tone="success">Equipment saved.</Notice>}
    {items.length ? <ul className="panel divide-y divide-line">{items.map((item) => <li key={item.id} className="flex flex-wrap items-center gap-3 px-4 py-4 sm:px-5">
      <div className="min-w-0 flex-1"><p className="font-semibold">{item.name}</p><p className="mt-1 text-xs text-muted">{item.category} · {item.total_quantity} total units · {item.unusable_quantity} unusable</p></div>
      <Status status={item.status} />
      <Link href={"/admin/equipment/" + item.id} className="button button-ghost"><Pencil className="size-4" /> Edit<span className="sr-only"> {item.name}</span></Link>
    </li>)}</ul> : <Empty icon={Package} title="No equipment yet" description="Add equipment to make it available in the borrowing catalog." href="/admin/equipment/new" action="Add equipment" />}
  </>;
}
