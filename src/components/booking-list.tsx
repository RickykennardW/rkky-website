import Link from "next/link";
import { ArrowRight, CalendarDays } from "lucide-react";
import { type Booking } from "@/lib/domain";
import { formatPeriod } from "@/lib/time";
import { Empty, Status } from "./ui";

export function BookingList({ bookings, admin = false, empty = "No bookings found.", showEmptyAction = true }: { bookings: Booking[]; admin?: boolean; empty?: string; showEmptyAction?: boolean }) {
  if (!bookings.length) return <Empty icon={CalendarDays} title={empty} description={admin ? "Requests and allocations will appear here as they are created." : "Bookings that match this view will appear here."}
    href={!admin && showEmptyAction ? "/book" : undefined} action="New booking" />;
  return <ul className="panel divide-y divide-line">
    {bookings.map((booking) => <li key={booking.id}>
      <Link href={(admin ? "/admin/bookings/" : "/bookings/") + booking.id} className="flex flex-wrap items-center gap-3 px-4 py-4 transition-colors hover:bg-raised sm:gap-5 sm:px-5">
        <span className="grid size-9 shrink-0 place-items-center rounded-lg border border-line bg-raised text-accent"><CalendarDays className="size-4" aria-hidden="true" /></span>
        <span className="min-w-0 flex-1 basis-[65%] sm:basis-auto">
          <span className="block break-words text-sm font-semibold">{booking.title}</span>
          <span className="mt-1 block text-xs leading-relaxed text-muted">{booking.rooms?.name ?? "Equipment only"} · {formatPeriod(booking.starts_at, booking.ends_at)}</span>
          {!admin && <span className="mt-1 block break-words text-xs leading-relaxed text-muted">
            <span className="font-mono">#{booking.id.slice(0, 8)}</span>
            {booking.booking_equipment.length > 0 && " · " + booking.booking_equipment.map((item) => item.quantity + " × " + (item.equipment?.name ?? "Equipment")).join(", ")}
          </span>}
          {admin && booking.profiles && <span className="mt-1 block text-xs text-muted">{booking.profiles.full_name}</span>}
        </span>
        <span className="ml-auto"><Status status={booking.status} /></span><ArrowRight className="size-4 shrink-0 text-muted" aria-hidden="true" />
      </Link>
    </li>)}
  </ul>;
}
