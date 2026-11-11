import Link from "next/link";
import { Package } from "lucide-react";
import { CatalogFilter, EquipmentCard } from "@/components/catalog";
import { Empty, Notice, PageHeading } from "@/components/ui";
import { getAvailability, getEquipment, getWorkspaceIdentity } from "@/lib/data";
import { demoEquipment } from "@/lib/demo";
import { categories, type Search } from "@/lib/domain";
import { periodQuery, schedule, searchPeriod, value } from "@/lib/time";

export const metadata = { title: "Equipment" };
export default async function EquipmentDirectory({ searchParams }: { searchParams: Promise<Search> }) {
  const search = await searchParams;
  const { client, preview } = await getWorkspaceIdentity();
  const period = searchPeriod(search);
  const range = schedule(period);
  const [items, availability] = await Promise.all([client ? getEquipment(client) : demoEquipment, client && range ? getAvailability(client, range) : null]);
  const category = value(search, "category");
  const filtered = items.filter((item) => !category || item.category === category);
  return <>
    <PageHeading title="Equipment" description="Compare equipment and check quantities for the full borrowing period." />
    {preview && <Notice>Fictional equipment catalog. <Link href="/setup" className="underline">Connect Supabase</Link> to check stock for a schedule.</Notice>}
    <CatalogFilter period={period} preview={preview} clear="/equipment">
      <label className="label w-48">Category<select name="category" className="input" defaultValue={category}><option value="">All categories</option>{categories.map((name) => <option key={name}>{name}</option>)}</select></label>
    </CatalogFilter>
    {!preview && Object.values(period).some(Boolean) && !range && <Notice tone="error">Enter both dates and times, with the end after the start.</Notice>}
    {filtered.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{filtered.map((item) => <EquipmentCard key={item.id} item={item} available={availability?.equipment[item.id]} query={range ? periodQuery(period) : undefined} preview={preview} />)}</div>
      : <Empty icon={Package} title="No equipment found" description="Choose another category or clear the filters." href="/equipment" action="Clear filters" />}
  </>;
}
