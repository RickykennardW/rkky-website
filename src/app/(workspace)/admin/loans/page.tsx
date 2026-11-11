import Link from "next/link";
import { ArrowRight, PackageCheck } from "lucide-react";
import { Empty, PageHeading, Status } from "@/components/ui";
import { getBookings, requireAdmin } from "@/lib/data";
import type { Search } from "@/lib/domain";
import { formatStamp, value } from "@/lib/time";

export const metadata = { title: "Equipment loans" };
export default async function Loans({ searchParams }: { searchParams: Promise<Search> }) {
  const { client } = await requireAdmin();
  const selection = value(await searchParams, "status");
  const status = ["outstanding", "returned"].includes(selection) ? selection : "all";
  const bookings = await getBookings(client);
  const history = bookings.flatMap((booking) => booking.equipment_loans.map((loan) => ({ booking, loan })))
    .filter(({ loan }) => status === "all" || (status === "returned" ? loan.returned_at : !loan.returned_at))
    .sort((a, b) => b.loan.picked_up_at.localeCompare(a.loan.picked_up_at));
  return <>
    <PageHeading title="Equipment loans" description="Review pickup and return history, including damaged and missing stock." />
    <nav aria-label="Filter equipment loans" className="flex flex-wrap gap-2">{[["all", "All loans"], ["outstanding", "Awaiting return"], ["returned", "Returned"]].map(([key, label]) =>
      <Link key={key} href={key === "all" ? "/admin/loans" : "/admin/loans?status=" + key} className={"button " + (status === key ? "button-primary" : "button-ghost")} aria-current={status === key ? "page" : undefined}>{label}</Link>)}</nav>
    {history.length ? <ul className="panel divide-y divide-line">{history.map(({ booking, loan }) => {
      const name = booking.booking_equipment.find((item) => item.equipment_id === loan.equipment_id)?.equipment?.name;
      const overdue = !loan.returned_at && new Date(booking.ends_at) < new Date();
      return <li key={booking.id + loan.equipment_id} className="flex flex-wrap items-center gap-3 px-4 py-4 sm:px-5">
        <div className="min-w-0 flex-1"><p className="font-semibold">{name} × {loan.quantity}</p>
          <p className="mt-1 text-xs text-muted">{booking.title} · Picked up {formatStamp(loan.picked_up_at)}</p>
          {loan.returned_at && <p className="mt-1 text-xs text-muted">Returned {formatStamp(loan.returned_at)} · {loan.missing} missing · {loan.damaged} damaged</p>}</div>
        <Status status={loan.returned_at ? "completed" : overdue ? "overdue" : "checked out"} />
        <Link href={"/admin/bookings/" + booking.id} className="button button-ghost">Open booking <ArrowRight className="size-4" /></Link>
      </li>;
    })}</ul> : <Empty icon={PackageCheck} title="No loans match this view" description="Pickup and return records appear here once an administrator records a handoff." />}
  </>;
}
