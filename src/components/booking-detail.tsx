import { CalendarDays, ClipboardList, LayoutGrid, Package, UserRound, Users } from "lucide-react";
import type { Booking } from "@/lib/domain";
import { planSchema } from "@/lib/planner";
import { formatPeriod, formatStamp } from "@/lib/time";
import { Status } from "./ui";

export function BookingDetail({ booking, admin }: { booking: Booking; admin?: boolean }) {
  const plan = planSchema.safeParse(booking.planner);
  return <>
    <section className="panel grid gap-5 p-5 sm:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="section-title">Reservation details</h2>
        <p className="mt-1 break-all text-xs text-muted">Booking ID {booking.id}</p></div><Status status={booking.status} /></div>
      <dl className="grid gap-4 sm:grid-cols-2">
        {[
          { icon: CalendarDays, label: "Schedule", detail: formatPeriod(booking.starts_at, booking.ends_at) },
          { icon: ClipboardList, label: "Room", detail: booking.rooms ? booking.rooms.name + " · " + booking.rooms.location : "Equipment only" },
          { icon: Users, label: "Attendance", detail: booking.participants + " participants · " + booking.committee + " committee members" },
          ...(admin && booking.profiles ? [{ icon: UserRound, label: "Requested by", detail: booking.profiles.full_name + " · " + booking.profiles.email }] : []),
        ].map(({ icon: Icon, label, detail }) => <div className="min-w-0 rounded-lg border border-line bg-[#0e141c] p-3" key={label}>
          <dt className="flex items-center gap-2 text-xs font-medium text-muted"><Icon className="size-4 text-accent" />{label}</dt>
          <dd className="mt-2 text-sm wrap-anywhere">{detail}</dd>
        </div>)}
      </dl>
      <div><h3 className="text-sm font-semibold">Purpose</h3><p className="muted mt-2 whitespace-pre-wrap wrap-anywhere">{booking.description}</p></div>
      {booking.review_note && <div className="rounded-lg border border-line bg-raised p-3"><h3 className="text-sm font-semibold">Review note</h3><p className="muted mt-2 whitespace-pre-wrap">{booking.review_note}</p></div>}
    </section>
    <section className="panel grid gap-4 p-5 sm:p-6">
      <h2 className="section-title flex items-center gap-2"><Package className="size-5 text-accent" /> Equipment and returns</h2>
      {booking.booking_equipment.length ? <ul className="divide-y divide-line">{booking.booking_equipment.map((item) => {
        const loan = booking.equipment_loans.find((loan) => loan.equipment_id === item.equipment_id);
        return <li className="grid gap-2 py-3" key={item.equipment_id}>
          <div className="flex flex-wrap justify-between gap-2 text-sm"><strong>{item.equipment?.name ?? "Equipment"}</strong><span className="text-muted">{item.quantity} units</span></div>
          {loan && <p className="text-xs leading-relaxed text-muted">Picked up {formatStamp(loan.picked_up_at)}{loan.returned_at ? " · Returned " + formatStamp(loan.returned_at) + " · " + loan.missing + " missing, " + loan.damaged + " damaged" : " · Return pending"}</p>}
          {loan?.return_note && <p className="muted">{loan.return_note}</p>}
          {admin && loan?.allocation_warning && <p className="text-sm text-warning">Stock loss affected an approved allocation at the time of return.</p>}
        </li>;
      })}</ul> : <p className="muted">No equipment requested.</p>}
    </section>
    {plan.success && <section className="panel grid gap-2 p-5 sm:p-6">
      <h2 className="section-title flex items-center gap-2"><LayoutGrid className="size-5 text-accent" /> Saved room plan</h2>
      <p className="muted">{plan.data.eventType} · {plan.data.arrangement} · {plan.data.chairs} chairs · {plan.data.tables} tables</p>
      <p className="muted">{plan.data.microphones} microphones · {plan.data.projectors} projectors · {plan.data.cables} extension cables</p>
      <p className="muted">{[plan.data.presentation && "Presentation area", plan.data.registrationDesk && "Registration desk", plan.data.discussion && "Group discussion"].filter(Boolean).join(" · ") || "Standard setup"}</p>
    </section>}
  </>;
}
