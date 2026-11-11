"use client";

import { useState } from "react";
import Link from "next/link";
import { ArrowRight, Armchair, ClipboardCheck, RotateCcw, Ruler } from "lucide-react";
import type { Room } from "@/lib/domain";
import { estimate, eventTypes, generatePlan, layouts, planSchema, plannerRules, type LayoutItem, type Plan, type PlannerInput } from "@/lib/planner";
import { Notice } from "./ui";

const colors: Record<LayoutItem["kind"], { fill: string; stroke: string }> = {
  chair: { fill: "#8aaffc", stroke: "#b4cbff" }, table: { fill: "#2f7180", stroke: "#79c6c8" },
  stage: { fill: "#283f65", stroke: "#82a6f0" }, registration: { fill: "#51402b", stroke: "#e1b272" },
  blocked: { fill: "#422e37", stroke: "#bb7982" }, fixed: { fill: "#3e3658", stroke: "#ac98ce" },
  clearance: { fill: "#25344a", stroke: "#374f70" },
};

function RoomDrawing({ room, items }: { room: Room; items: LayoutItem[] }) {
  const { width, depth, door } = room.layout;
  const northSouth = door.wall === "north" || door.wall === "south";
  const wall = door.wall === "north" || door.wall === "west" ? 0 : northSouth ? depth : width;
  return <svg className="block w-full max-h-[520px]" viewBox={[-0.6, -0.65, width + 1.2, depth + 1.3].join(" ")}
    role="img" aria-label={"Room layout for " + room.name + ", including furniture and marked door clearance"}>
    <defs><pattern id={"room-grid-" + room.id} width="1" height="1" patternUnits="userSpaceOnUse">
      <path d="M1 0H0V1" fill="none" stroke="#28374b" strokeWidth=".025" />
    </pattern></defs>
    <rect x="0" y="0" width={width} height={depth} rx=".12" fill="#131e2a" stroke="#557392" strokeWidth=".07" />
    <rect x="0" y="0" width={width} height={depth} fill={"url(#room-grid-" + room.id + ")"} />
    {items.map(({ x, y, width, depth, kind, label }, index) => <g key={index}>
      <rect x={x} y={y} width={width} height={depth} rx={kind === "chair" ? ".09" : ".05"} fill={colors[kind].fill} stroke={colors[kind].stroke} strokeWidth=".025"
        opacity={kind === "clearance" ? ".7" : "1"} />
      {(label || kind === "stage" || kind === "registration") && width >= 1 && <text x={x + width / 2} y={y + depth / 2} textAnchor="middle" dominantBaseline="middle" fontSize={Math.min(0.23, width / 9)} fill="#edf4ff">
        {(label ?? (kind === "stage" ? "Presentation" : "Check-in")).slice(0, 18)}
      </text>}
    </g>)}
    <line x1={northSouth ? door.offset : wall} x2={northSouth ? door.offset + door.width : wall}
      y1={northSouth ? wall : door.offset} y2={northSouth ? wall : door.offset + door.width} stroke="#82ddab" strokeWidth=".13" strokeLinecap="round" />
    <text x={width / 2} y={depth + 0.45} textAnchor="middle" fontSize=".26" fill="#9caabc">{width} m × {depth} m</text>
  </svg>;
}

type Amounts = Pick<Plan, "chairs" | "tables" | "microphones" | "projectors" | "cables">;
type Props = {
  room: Room;
  initialPlan?: Plan;
  attendance?: { participants: number; committee: number };
  onSave?: (plan: Plan) => void;
  allowBooking?: boolean;
};

export function Planner({ room, initialPlan, attendance, onSave, allowBooking }: Props) {
  const [input, setInput] = useState<PlannerInput>(initialPlan ?? {
    eventType: "Seminar", participants: Math.min(24, room.capacity), committee: room.capacity > 28 ? 4 : 0,
    presentation: true, registrationDesk: false, discussion: false,
  });
  const [arrangement, setArrangement] = useState<Plan["arrangement"] | "">(initialPlan?.arrangement ?? "");
  const [overrides, setOverrides] = useState<Partial<Amounts>>(initialPlan ? {
    chairs: initialPlan.chairs, tables: initialPlan.tables, microphones: initialPlan.microphones, projectors: initialPlan.projectors, cables: initialPlan.cables,
  } : {});
  const suggested = estimate({ ...input, ...attendance });
  const plan: Plan = { ...suggested, ...overrides, arrangement: arrangement || suggested.arrangement };
  const preview = planSchema.safeParse(plan).success
    ? generatePlan(room, plan)
    : { items: [], error: "Enter at least one participant and whole, non-negative equipment quantities." };
  const quantities: { key: keyof Amounts; label: string }[] = [
    { key: "chairs", label: "Chairs" }, { key: "tables", label: "Tables" }, { key: "microphones", label: "Microphones" },
    { key: "projectors", label: "Projectors" }, { key: "cables", label: "Extension cables" },
  ];
  const planQuery = new URLSearchParams({ room: room.id, plan: JSON.stringify(plan) }).toString();
  return <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">
    <section className="panel grid content-start gap-5 p-4 sm:p-5">
      <div className="flex items-center gap-2"><Armchair className="size-5 text-accent" /><h2 className="section-title">Event setup</h2></div>
      <div className="grid grid-cols-2 gap-3">
        <label className="label col-span-2">Event type<select className="input" value={input.eventType} onChange={(event) => setInput({ ...input, eventType: event.target.value as Plan["eventType"] })}>
          {eventTypes.map((type) => <option key={type}>{type}</option>)}</select></label>
        {attendance ? <p className="muted col-span-2">{attendance.participants} participants · {attendance.committee} committee members</p>
          : <><label className="label">Participants<input className="input" type="number" min={1} max={500} value={input.participants} onChange={(event) => setInput({ ...input, participants: Number(event.target.value) })} /></label>
            <label className="label">Committee<input className="input" type="number" min={0} max={500} value={input.committee} onChange={(event) => setInput({ ...input, committee: Number(event.target.value) })} /></label></>}
      </div>
      <div className="grid gap-2.5">
        {([["presentation", "Presentation required"], ["registrationDesk", "Registration desk required"], ["discussion", "Group discussion required"]] as const).map(([field, label]) =>
          <label key={field} className="flex min-h-8 items-center gap-2.5 text-sm"><input type="checkbox" className="checkbox" checked={input[field]} onChange={(event) => setInput({ ...input, [field]: event.target.checked })} />{label}</label>)}
      </div>
      <div className="grid gap-3 border-t border-line pt-4">
        <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="font-semibold">Suggested equipment</h3>
          <button className="flex min-h-8 items-center gap-1.5 text-xs font-semibold text-accent hover:text-ink" type="button" onClick={() => { setOverrides({}); setArrangement(""); }}><RotateCcw className="size-3.5" /> Reset estimate</button></div>
        <div className="grid grid-cols-2 gap-3">{quantities.map(({ key, label }) => <label key={key} className="label">{label}
          <input className="input" type="number" min={0} max={500} value={plan[key]} onChange={(event) => setOverrides({ ...overrides, [key]: Number(event.target.value) })} /></label>)}</div>
        <p className="text-xs leading-relaxed text-muted">The estimate seats all participants and {plannerRules.committeeSeatShare * 100}% of committee members, rounded up. Workshops use tables of four. A registration desk uses one table. Presentation equipment is an estimate you can adjust and request separately.</p>
      </div>
    </section>
    <section className="panel grid content-start gap-4 p-4 sm:p-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div><h2 className="section-title">Layout preview</h2><p className="mt-1 flex items-center gap-1.5 text-xs text-muted"><Ruler className="size-3.5" /> {room.layout.width} m × {room.layout.depth} m · {room.capacity} person capacity</p></div>
        <label className="label min-w-36">Arrangement<select className="input" value={plan.arrangement} onChange={(event) => setArrangement(event.target.value as Plan["arrangement"])}>
          {layouts.map((layout) => <option key={layout}>{layout}</option>)}</select></label>
      </div>
      {preview.error && <Notice tone="error">{preview.error}</Notice>}
      <div className="rounded-lg border border-line bg-[#0d141e] p-2"><RoomDrawing room={room} items={preview.items} /></div>
      <ul aria-label="Map legend" className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted">
        {([["chair", "Chairs"], ["table", "Tables"], ["fixed", "Fixed equipment"], ["blocked", "Blocked zones"], ["clearance", "Clearance"], ["door", "Door"]] as const).map(([key, label]) =>
          <li key={key} className="flex items-center gap-2"><span className="size-2.5 rounded-sm" style={{ background: key === "door" ? "#82ddab" : colors[key].fill }} />{label}</li>)}
      </ul>
      <p className="text-xs leading-relaxed text-muted">Planning aid only. Confirm the final setup and access requirements with campus staff.</p>
      {!preview.error && (onSave
        ? <button type="button" onClick={() => onSave(plan)} className="button button-primary justify-self-start"><ClipboardCheck className="size-4" /> Save plan to booking</button>
        : allowBooking ? <Link href={"/book?" + planQuery} className="button button-primary justify-self-start">Use this plan <ArrowRight className="size-4" /></Link> : null)}
    </section>
  </div>;
}

export function PlannerDirectory({ rooms, selected, allowBooking }: { rooms: Room[]; selected?: string; allowBooking: boolean }) {
  const [id, setId] = useState(rooms.some((room) => room.id === selected) ? selected : rooms[0]?.id);
  const room = rooms.find((room) => room.id === id);
  if (!room) return <Notice>No rooms with layout configuration are available.</Notice>;
  return <>
    <label className="label panel max-w-md p-4">Room<select className="input" value={id} onChange={(event) => setId(event.target.value)}>
      {rooms.map(({ id, name }) => <option key={id} value={id}>{name}</option>)}
    </select></label>
    <Planner key={room.id} room={room} allowBooking={allowBooking} />
  </>;
}
