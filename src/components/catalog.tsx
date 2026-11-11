import Image from "next/image";
import Link from "next/link";
import { ArrowRight, AudioLines, Building2, Cable, CalendarRange, LayoutGrid, MapPin, Package, Presentation, Search, Users } from "lucide-react";
import type { Equipment, Period, Room } from "@/lib/domain";
import { Status } from "./ui";

export function RoomCard({ room, available, query, preview, eager }: { room: Room; available?: boolean; query?: string; preview?: boolean; eager?: boolean }) {
  return <article className="panel group flex flex-col overflow-hidden">
    <Link href={"/rooms/" + room.id + (query ? "?" + query : "")} className="relative block aspect-[2.2] overflow-hidden bg-raised" aria-label={"View " + room.name}>
      {room.photo ? <Image src={"/rooms/" + room.photo + ".png"} alt="" fill loading={eager ? "eager" : "lazy"} sizes="(min-width: 1280px) 330px, (min-width: 640px) 45vw, 100vw" className="object-cover transition-transform duration-300 group-hover:scale-[1.025]" />
        : <Building2 aria-hidden="true" className="absolute inset-0 m-auto size-10 text-muted" />}
      <div className="absolute inset-0 bg-linear-to-t from-[#0b0f14]/55 via-transparent to-transparent" />
      <div className="absolute bottom-3 left-3">
        {preview ? <span className="rounded-md bg-[#111820]/90 px-2.5 py-1.5 text-xs font-medium text-[#dce5f2]">Sample room</span>
          : <Status status={!room.active ? "unavailable" : available === undefined ? "check schedule" : available ? "available" : "reserved"} />}
      </div>
    </Link>
    <div className="grid flex-1 content-start gap-3 p-4">
      <div><h2 className="font-semibold tracking-tight">{room.name}</h2>
        <p className="mt-1 flex items-center gap-1.5 text-xs text-muted"><MapPin className="size-3.5 shrink-0" />{room.location}</p></div>
      <p className="text-sm leading-relaxed text-muted">{room.description}</p>
      <div className="flex flex-wrap gap-1.5">
        {room.room_facilities.map(({ facility }) => <span className="chip" key={facility}>{facility}</span>)}
      </div>
    </div>
    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3">
      <span className="flex items-center gap-2 text-xs font-medium text-muted"><Users className="size-4" aria-hidden="true" />{room.capacity} people</span>
      <Link href={"/rooms/" + room.id + (query ? "?" + query : "")} className="flex min-h-9 items-center gap-1.5 text-sm font-semibold text-accent hover:text-ink">View room <ArrowRight className="size-4" aria-hidden="true" /></Link>
    </div>
  </article>;
}

const equipmentIcons = { Audio: AudioLines, Presentation, Furniture: LayoutGrid, Power: Cable, Accessories: Package };

export function EquipmentCard({ item, available, query, preview }: { item: Equipment; available?: number; query?: string; preview: boolean }) {
  const Icon = equipmentIcons[item.category] ?? Package;
  const href = preview ? "/setup" : "/book?equipment=" + item.id + (query ? "&" + query : "");
  return <article className="panel grid gap-4 p-5">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <span className="grid size-11 place-items-center rounded-lg border border-[#304362] bg-[#18263b] text-accent"><Icon className="size-5" aria-hidden="true" /></span>
      <span className="chip">{item.category}</span>
    </div>
    <div><h2 className="section-title">{item.name}</h2><p className="muted mt-1">{item.description}</p></div>
    <div className="flex flex-wrap items-center gap-2 text-sm text-muted">
      <span>{item.total_quantity} total units</span>
      {!preview && <Status status={item.status !== "available" ? item.status : available === undefined ? "check schedule" : available ? "available" : "reserved"} />}
      {available !== undefined && <span className="font-medium text-ink">{available} available for this period</span>}
    </div>
    <Link className="button button-ghost justify-self-start" href={href}>{preview ? "Booking setup" : "Request equipment"}<ArrowRight className="size-4" /></Link>
  </article>;
}

export function ScheduleFields({ period, disabled, required }: { period: Period; disabled?: boolean; required?: boolean }) {
  return <fieldset disabled={disabled} className="grid min-w-0 grid-cols-2 gap-3 disabled:opacity-50 sm:grid-cols-4">
    <legend className="sr-only">Reservation schedule in Jakarta time</legend>
    <label className="label">Start date<input className="input" type="date" name="date" defaultValue={period.date} required={required} /></label>
    <label className="label">Start time<input className="input" type="time" name="start" defaultValue={period.start} required={required} /></label>
    <label className="label">End date<input className="input" type="date" name="endDate" defaultValue={period.endDate} required={required} /></label>
    <label className="label">End time<input className="input" type="time" name="end" defaultValue={period.end} required={required} /></label>
  </fieldset>;
}

export function CatalogFilter({ children, period, preview, clear }: { children?: React.ReactNode; period: Period; preview: boolean; clear: string }) {
  return <form method="get" className="panel grid gap-4 p-4 sm:p-5">
    <div className="flex flex-wrap items-center gap-2 text-sm font-medium"><CalendarRange className="size-4 text-accent" aria-hidden="true" />
      {preview ? "Live availability needs a Supabase connection" : "Check a reservation period"} <span className="ml-auto text-xs text-muted">Asia/Jakarta · WIB</span></div>
    <ScheduleFields period={period} disabled={preview} />
    <div className="flex flex-wrap items-end gap-3">{children}<div className="ml-auto flex flex-wrap gap-2">
      <Link href={clear} className="button button-ghost">Clear</Link><button className="button button-primary"><Search className="size-4" aria-hidden="true" /> Apply filters</button>
    </div></div>
  </form>;
}
