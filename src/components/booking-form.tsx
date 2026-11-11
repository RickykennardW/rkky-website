"use client";

import { useActionState, useState, type FormEvent } from "react";
import { ArrowRight, CalendarCheck, CalendarRange, LayoutGrid, LoaderCircle } from "lucide-react";
import { createBooking } from "@/lib/actions";
import type { Availability, Equipment, Period, Room } from "@/lib/domain";
import type { Plan } from "@/lib/planner";
import { periodQuery, schedule } from "@/lib/time";
import { Feedback, Notice } from "./ui";
import { Planner } from "./planner";

export function BookingForm({ rooms, equipment, initialRoom, initialEquipment, initialPeriod, initialPlan }: {
  rooms: Room[]; equipment: Equipment[]; initialRoom?: string; initialEquipment?: string; initialPeriod: Period; initialPlan?: Plan;
}) {
  const [state, action, pending] = useActionState(createBooking, {});
  const [roomId, setRoomId] = useState(initialRoom ?? "");
  const [period, setPeriod] = useState(initialPeriod);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [participants, setParticipants] = useState(initialPlan?.participants ?? 1);
  const [committee, setCommittee] = useState(initialPlan?.committee ?? 0);
  const [items, setItems] = useState<Record<string, number>>(initialEquipment ? { [initialEquipment]: 1 } : {});
  const [plan, setPlan] = useState<Plan | null>(initialPlan ?? null);
  const [plannerOpen, setPlannerOpen] = useState(false);
  const [review, setReview] = useState(false);
  const [reviewError, setReviewError] = useState("");
  const [availability, setAvailability] = useState<{ key: string; data: Availability }>();
  const [checking, setChecking] = useState(false);
  const [availabilityError, setAvailabilityError] = useState("");
  const room = rooms.find((entry) => entry.id === roomId);
  const selected = equipment.filter((item) => items[item.id] > 0);
  const query = periodQuery(period);
  const live = availability?.key === query ? availability.data : undefined;

  async function checkAvailability() {
    if (!schedule(period, true)) { setAvailabilityError("Choose a future start and an end after it."); return; }
    setAvailabilityError("");
    setChecking(true);
    try {
      const response = await fetch("/api/availability?" + query, { cache: "no-store" });
      if (!response.ok) throw new Error("Availability could not be checked. Try again.");
      setAvailability({ key: query, data: await response.json() as Availability });
    } catch { setAvailabilityError("Availability could not be checked. Try again."); }
    finally { setChecking(false); }
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    if (review) return;
    event.preventDefault();
    setReviewError("");
    if (!schedule(period, true)) { setReviewError("Choose a future start and an end after it."); return; }
    if (!room && !selected.length) { setReviewError("Choose a room or request at least one equipment item."); return; }
    if (room && participants + committee > room.capacity) { setReviewError("Total attendance exceeds the room capacity."); return; }
    if ((room && live?.rooms[room.id] === false) || selected.some((item) => live && items[item.id] > live.equipment[item.id])) {
      setReviewError("The selected room or equipment is unavailable for this period.");
      return;
    }
    setReview(true);
    requestAnimationFrame(() => document.getElementById("review-booking")?.focus());
  }
  function updateAttendance(key: "participants" | "committee", value: number) {
    if (key === "participants") setParticipants(value);
    else setCommittee(value);
    setPlan(null);
  }

  return <form action={action} onSubmit={submit} onChange={() => { setReview(false); setReviewError(""); }} className="grid gap-5">
    <input type="hidden" name="items" value={JSON.stringify(selected.map((item) => ({ equipment_id: item.id, quantity: items[item.id] })))} />
    <input type="hidden" name="planner" value={plan ? JSON.stringify(plan) : ""} />
    <section className="panel grid gap-4 p-4 sm:p-6">
      <div><h2 className="section-title">Room and schedule</h2><p className="muted mt-1">Times are in Asia/Jakarta (WIB).</p></div>
      <label className="label">Room<select className="input" name="roomId" value={roomId} onChange={(event) => { setRoomId(event.target.value); setPlan(null); setPlannerOpen(false); }}>
        <option value="">Equipment only</option>{rooms.filter((entry) => entry.active).map((entry) => <option key={entry.id} value={entry.id}>{entry.name} · {entry.capacity} people</option>)}
      </select></label>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">{([["date", "Start date", "date"], ["start", "Start time", "time"], ["endDate", "End date", "date"], ["end", "End time", "time"]] as const).map(([key, label, type]) =>
        <label className="label" key={key}>{label}<input className="input" type={type} required name={key} value={period[key]} onChange={(event) => { setPeriod({ ...period, [key]: event.target.value, ...(key === "date" && period.endDate === period.date ? { endDate: event.target.value } : {}) }); setAvailabilityError(""); }} /></label>)}</div>
      <div className="flex flex-wrap items-center gap-3">
        <button type="button" className="button button-secondary" onClick={checkAvailability} disabled={checking}>
          {checking ? <LoaderCircle className="size-4 motion-safe:animate-spin" /> : <CalendarRange className="size-4" />}Check availability</button>
        {live && room && <span role="status" className={"text-sm font-medium " + (live.rooms[room.id] ? "text-positive" : "text-negative")}>{live.rooms[room.id] ? "Room available for this period" : "Room unavailable for this period"}</span>}
        {live && !room && <span role="status" className="text-sm text-positive">Equipment quantities updated below.</span>}
      </div>
      {availabilityError && <Notice tone="error">{availabilityError}</Notice>}
    </section>
    <section className="panel grid gap-4 p-4 sm:p-6">
      <h2 className="section-title">Event details</h2>
      <label className="label">Event name<input className="input" name="title" required minLength={2} maxLength={120} value={title} onChange={(event) => setTitle(event.target.value)} placeholder="e.g. Student committee workshop" /></label>
      <label className="label">Purpose or description<textarea className="input min-h-24 resize-y" name="description" required minLength={2} maxLength={2000} value={description} onChange={(event) => setDescription(event.target.value)} /></label>
      <div className="grid grid-cols-2 gap-3">
        <label className="label">Participants<input className="input" name="participants" type="number" min={1} max={500} required value={participants} onChange={(event) => updateAttendance("participants", Number(event.target.value))} /></label>
        <label className="label">Committee members<input className="input" name="committee" type="number" min={0} max={500} required value={committee} onChange={(event) => updateAttendance("committee", Number(event.target.value))} /></label>
      </div>
      {room && <div className="flex flex-wrap items-center gap-3">
        <button type="button" aria-expanded={plannerOpen} className="button button-secondary" onClick={() => setPlannerOpen(!plannerOpen)}><LayoutGrid className="size-4" /> {plannerOpen ? "Close planner" : "Open room planner"}</button>
        {plan && <span role="status" className="text-sm text-positive">{plan.arrangement} plan attached. <button type="button" className="ml-1 underline" onClick={() => { setPlan(null); setReview(false); }}>Remove plan</button></span>}
      </div>}
    </section>
    {room && plannerOpen && <Planner key={room.id} room={room} attendance={{ participants, committee }} initialPlan={plan ?? undefined} onSave={(configuration) => { setPlan(configuration); setPlannerOpen(false); setReview(false); }} />}
    <section className="panel grid gap-4 p-4 sm:p-6">
      <div><h2 className="section-title">Equipment</h2><p className="muted mt-1">Set a quantity for each item you need. The server checks stock before saving.</p></div>
      {equipment.length ? <ul className="divide-y divide-line">{equipment.map((item) => {
        const max = live ? live.equipment[item.id] ?? 0 : item.status === "available" ? item.total_quantity - item.unusable_quantity : 0;
        return <li key={item.id} className="flex items-center justify-between gap-3 py-3">
          <div className="min-w-0"><p className="text-sm font-medium break-words">{item.name}</p><p className="mt-1 text-xs text-muted">{item.category} · {live ? max + " available" : max + " usable units"}</p></div>
          <label className="w-21 shrink-0"><span className="sr-only">{item.name} quantity</span><input className="input" type="number" min={0} max={max} value={items[item.id] ?? 0}
            onChange={(event) => setItems({ ...items, [item.id]: Number(event.target.value) })} /></label>
        </li>;
      })}</ul> : <p className="muted">No equipment has been added to the catalog.</p>}
    </section>
    {reviewError && <Notice tone="error">{reviewError}</Notice>}
    <Feedback state={state} />
    {review ? <section id="review-booking" tabIndex={-1} className="panel grid gap-4 border-[#405f8e] p-5">
      <h2 className="section-title">Review your request</h2>
      <div className="grid gap-1 text-sm"><p className="font-semibold">{title}</p>
        <p className="text-muted">{room?.name ?? "Equipment only"} · {period.date} {period.start} to {period.endDate} {period.end} WIB</p>
        <p className="text-muted">{participants} participants · {committee} committee members{plan ? " · " + plan.arrangement + " plan" : ""}</p>
        {selected.map((item) => <p className="text-muted" key={item.id}>{item.name} × {items[item.id]}</p>)}
        <p className="muted mt-2">{description}</p>
      </div>
      <div className="flex flex-wrap gap-2"><button type="submit" className="button button-primary" disabled={pending}>{pending ? <LoaderCircle className="size-4 motion-safe:animate-spin" /> : <CalendarCheck className="size-4" />} Submit request</button>
        <button type="button" onClick={() => setReview(false)} className="button button-secondary" disabled={pending}>Edit details</button></div>
    </section> : <button className="button button-primary justify-self-start" type="submit">Review request <ArrowRight className="size-4" /></button>}
  </form>;
}
