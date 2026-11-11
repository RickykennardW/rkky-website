import Link from "next/link";
import { bookingStatuses } from "@/lib/domain";

export function BookingFilters({ current, path }: { current: string; path: string }) {
  return <nav aria-label="Filter bookings by status" className="flex flex-wrap gap-2">
    {["all", ...bookingStatuses].map((status) => <Link href={status === "all" ? path : path + "?status=" + status} key={status}
      aria-current={current === status ? "page" : undefined}
      className={"button min-h-9 capitalize " + (current === status ? "button-primary" : "button-ghost")}>{status}</Link>)}
  </nav>;
}
