"use client";

import { useActionState, useState } from "react";
import { LoaderCircle, Plus, Save, Trash2 } from "lucide-react";
import { saveEquipment, saveRoom } from "@/lib/actions";
import { categories, defaultLayout, equipmentStatuses, facilities, type Equipment, type LayoutZone, type Room } from "@/lib/domain";
import { Feedback } from "./ui";

function ZoneEditor({ title, zones, setZones }: { title: string; zones: LayoutZone[]; setZones: (zones: LayoutZone[]) => void }) {
  return <div className="grid gap-3">
    <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="font-medium">{title}</h3>
      <button type="button" className="button button-ghost min-h-8" onClick={() => setZones([...zones, { label: "", x: 0, y: 0, width: 1, depth: 1 }])} disabled={zones.length >= 25}><Plus className="size-4" /> Add zone</button></div>
    {zones.map((zone, index) => <fieldset key={index} className="grid gap-3 rounded-lg border border-line bg-[#0e141c] p-3">
      <legend className="sr-only">{title} {index + 1}</legend>
      <div className="flex items-end gap-2">
        <label className="label flex-1">Zone name<input className="input" value={zone.label} required maxLength={60} onChange={(event) => setZones(zones.map((item, position) => position === index ? { ...item, label: event.target.value } : item))} /></label>
        <button type="button" className="button button-danger px-3" aria-label={"Remove zone " + (index + 1)} onClick={() => setZones(zones.filter((_, position) => position !== index))}><Trash2 className="size-4" /></button>
      </div>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {(["x", "y", "width", "depth"] as const).map((key) => <label className="label capitalize" key={key}>{key} (m)<input type="number" className="input" min={key === "x" || key === "y" ? 0 : 0.1} max={60} step={0.1} value={zone[key]} required
          onChange={(event) => setZones(zones.map((item, position) => position === index ? { ...item, [key]: Number(event.target.value) } : item))} /></label>)}
      </div>
    </fieldset>)}
    {!zones.length && <p className="text-xs text-muted">No zones added.</p>}
  </div>;
}

export function RoomEditor({ room }: { room?: Room }) {
  const geometry = room?.layout ?? defaultLayout;
  const [state, action, pending] = useActionState(saveRoom, {});
  const [values, setValues] = useState({
    name: room?.name ?? "", location: room?.location ?? "", description: room?.description ?? "",
    capacity: String(room?.capacity ?? 40), chairs: String(room?.chairs ?? 40), tables: String(room?.tables_count ?? 12),
    photo: room?.photo ?? "", width: String(geometry.width), depth: String(geometry.depth), doorWall: geometry.door.wall,
    doorWidth: String(geometry.door.width), doorOffset: String(geometry.door.offset), doorClearance: String(geometry.door.clearance),
  });
  const [active, setActive] = useState(room?.active ?? true);
  const [selected, setSelected] = useState(room?.room_facilities.map(({ facility }) => facility) ?? []);
  const [blocked, setBlocked] = useState(geometry.blocked);
  const [fixed, setFixed] = useState(geometry.fixed);
  const bind = (key: keyof typeof values) => ({
    name: key, value: values[key], onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
      setValues((current) => ({ ...current, [key]: event.target.value })),
  });
  return <form action={action} className="grid gap-4">
    <input type="hidden" name="id" value={room?.id ?? ""} />
    <input type="hidden" name="blocked" value={JSON.stringify(blocked)} />
    <input type="hidden" name="fixed" value={JSON.stringify(fixed)} />
    <section className="panel grid gap-4 p-5 sm:p-6">
      <h2 className="section-title">Room information</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="label">Room name<input className="input" required minLength={2} maxLength={100} {...bind("name")} /></label>
        <label className="label">Building or location<input className="input" required minLength={2} maxLength={120} {...bind("location")} /></label>
      </div>
      <label className="label">Description<textarea className="input min-h-24" required maxLength={1500} {...bind("description")} /></label>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        {([["capacity", "Capacity", 1], ["chairs", "Chairs", 0], ["tables", "Tables", 0]] as const).map(([key, label, min]) =>
          <label className="label" key={key}>{label}<input className="input" type="number" required min={min} max={500} {...bind(key)} /></label>)}
      </div>
      <label className="label">Illustrative photo<select className="input" {...bind("photo")}>
        <option value="">None</option><option value="seminar">Seminar room</option><option value="classroom">Classroom</option><option value="hall">Multipurpose hall</option>
      </select></label>
      <label className="flex min-h-9 items-center gap-3 text-sm"><input type="checkbox" className="checkbox" name="active" checked={active} onChange={(event) => setActive(event.target.checked)} /> Available for booking</label>
      <fieldset className="grid gap-2"><legend className="mb-2 text-sm font-medium">Facilities</legend><div className="flex flex-wrap gap-2">
        {facilities.map((facility) => <label key={facility} className="chip min-h-9 cursor-pointer"><input type="checkbox" className="checkbox" name="facilities" value={facility}
          checked={selected.includes(facility)} onChange={(event) => setSelected(event.target.checked ? [...selected, facility] : selected.filter((item) => item !== facility))} />{facility}</label>)}
      </div></fieldset>
    </section>
    <section className="panel grid gap-5 p-5 sm:p-6">
      <div><h2 className="section-title">Layout geometry</h2><p className="muted mt-1">Measure in metres. Zone coordinates start at the top-left. Door offset runs from the left on horizontal walls, and from the top on vertical walls.</p></div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        {([["width", "Room width", 3, 60], ["depth", "Room depth", 3, 60], ["doorOffset", "Door offset", 0, 60], ["doorWidth", "Door width", 0.8, 5], ["doorClearance", "Door clearance", 0.9, 5]] as const).map(([key, label, min, max]) =>
          <label className="label" key={key}>{label}<input className="input" type="number" required min={min} max={max} step={0.1} {...bind(key)} /></label>)}
        <label className="label">Door wall<select className="input capitalize" {...bind("doorWall")}>{["north", "south", "east", "west"].map((wall) => <option key={wall} value={wall}>{wall}</option>)}</select></label>
      </div>
      <ZoneEditor title="Blocked zones" zones={blocked} setZones={setBlocked} />
      <ZoneEditor title="Fixed equipment" zones={fixed} setZones={setFixed} />
    </section>
    <Feedback state={state} />
    <button className="button button-primary justify-self-start" type="submit" disabled={pending}>{pending ? <LoaderCircle className="size-4 motion-safe:animate-spin" /> : <Save className="size-4" />} Save room</button>
  </form>;
}

export function EquipmentEditor({ item }: { item?: Equipment }) {
  const [state, action, pending] = useActionState(saveEquipment, {});
  const [values, setValues] = useState({
    name: item?.name ?? "", description: item?.description ?? "", category: item?.category ?? "Audio",
    status: item?.status ?? "available", total: String(item?.total_quantity ?? 1), unusable: String(item?.unusable_quantity ?? 0),
  });
  const bind = (key: keyof typeof values) => ({
    name: key, value: values[key], onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
      setValues((current) => ({ ...current, [key]: event.target.value })),
  });
  return <form action={action} className="panel grid max-w-3xl gap-4 p-5 sm:p-6">
    <input type="hidden" name="id" value={item?.id ?? ""} />
    <label className="label">Equipment name<input className="input" required minLength={2} maxLength={100} {...bind("name")} /></label>
    <label className="label">Description<textarea className="input min-h-24" required maxLength={1500} {...bind("description")} /></label>
    <div className="grid grid-cols-2 gap-3">
      <label className="label">Category<select className="input" {...bind("category")}>{categories.map((category) => <option key={category}>{category}</option>)}</select></label>
      <label className="label">Status<select className="input capitalize" {...bind("status")}>{equipmentStatuses.map((status) => <option key={status}>{status}</option>)}</select></label>
      <label className="label">Total units<input className="input" type="number" min={0} max={10000} required {...bind("total")} /></label>
      <label className="label">Unusable units<input className="input" type="number" min={0} max={10000} required {...bind("unusable")} /></label>
    </div>
    <p className="muted">Unusable units include missing or damaged stock. Changes are checked against approved allocations and active loans.</p>
    <Feedback state={state} />
    <button className="button button-primary justify-self-start" type="submit" disabled={pending}>{pending ? <LoaderCircle className="size-4 motion-safe:animate-spin" /> : <Save className="size-4" />} Save equipment</button>
  </form>;
}
