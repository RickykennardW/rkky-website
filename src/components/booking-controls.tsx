"use client";

import { useActionState, useRef } from "react";
import { AlertTriangle, Check, LoaderCircle, PackageCheck, X } from "lucide-react";
import type { ActionState, Booking } from "@/lib/domain";
import { cancelBooking, completeRoom, recordPickup, recordReturn, reviewBooking } from "@/lib/actions";
import { Feedback } from "./ui";

type ServerAction = (state: ActionState, form: FormData) => Promise<ActionState>;

function Confirm({ title, description, trigger, submit, action, booking, children, danger }: {
  title: string; description: string; trigger: string; submit: string; action: ServerAction;
  booking: string; children?: React.ReactNode; danger?: boolean;
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const [state, dispatch, pending] = useActionState(action, {});
  return <>
    <button className={"button " + (danger ? "button-danger" : "button-secondary")} type="button" onClick={() => dialog.current?.showModal()}>
      {danger ? <AlertTriangle className="size-4" /> : <Check className="size-4" />}{trigger}</button>
    <dialog ref={dialog} aria-label={title} className="m-auto max-h-[90dvh] w-[min(92vw,560px)] overflow-auto rounded-xl border border-line bg-panel p-5 text-ink backdrop:bg-black/70 sm:p-6">
      <form action={dispatch} className="grid gap-4">
        <input type="hidden" name="booking" value={booking} />
        <div className="flex items-start justify-between gap-3"><div><h2 className="section-title">{title}</h2><p className="muted mt-2">{description}</p></div>
          <button className="button button-ghost px-2" type="button" aria-label="Close dialog" onClick={() => dialog.current?.close()}><X className="size-4" /></button>
        </div>
        {children}
        <Feedback state={state} />
        <div className="flex flex-wrap justify-end gap-2">
          <button className="button button-ghost" type="button" onClick={() => dialog.current?.close()}>Back</button>
          <button className={"button " + (danger ? "button-danger" : "button-primary")} disabled={pending} type="submit">
            {pending && <LoaderCircle className="size-4 motion-safe:animate-spin" />}{submit}</button>
        </div>
      </form>
    </dialog>
  </>;
}

export function StudentControls({ booking }: { booking: Booking }) {
  if (booking.status !== "pending") return null;
  return <div><Confirm title="Cancel this request?" description="This will close the pending request and remove it from the review queue."
    trigger="Cancel request" submit="Cancel request" action={cancelBooking} booking={booking.id} danger /></div>;
}

export function AdminControls({ booking, pickupOpen, ended }: { booking: Booking; pickupOpen: boolean; ended: boolean }) {
  const loans = booking.equipment_loans.filter((loan) => !loan.returned_at);
  const hasEquipment = booking.booking_equipment.length > 0;
  if (booking.status === "pending") return <section className="panel grid gap-3 p-5">
    <h2 className="section-title">Review request</h2><p className="muted">Approval checks room and equipment availability for the full reservation period.</p>
    <div className="flex flex-wrap gap-2">
      <Confirm title="Approve and allocate?" description="The room and equipment will be reserved if the schedule and stock are still available."
        trigger="Approve request" submit="Approve booking" action={reviewBooking} booking={booking.id}>
        <input type="hidden" name="decision" value="approve" />
      </Confirm>
      <Confirm title="Reject this request?" description="The requester will be able to see the reason you provide."
        trigger="Reject request" submit="Reject booking" action={reviewBooking} booking={booking.id} danger>
        <input type="hidden" name="decision" value="reject" />
        <label className="label">Rejection reason<textarea className="input min-h-24" name="reason" required minLength={3} maxLength={1000} /></label>
      </Confirm>
    </div>
  </section>;
  if (booking.status !== "approved") return null;
  return <section className="panel grid gap-3 p-5">
    <h2 className="section-title flex items-center gap-2"><PackageCheck className="size-5 text-accent" />{hasEquipment ? "Equipment handoff" : "Room usage"}</h2>
    {loans.length ? <div><Confirm title="Record equipment return" description="Enter the missing and damaged units for every item. The return will be recorded with your account and time."
      trigger="Record return" submit="Complete return" action={recordReturn} booking={booking.id}>
      <div className="grid gap-3">{loans.map((loan) => <fieldset key={loan.equipment_id} className="grid grid-cols-2 gap-3 rounded-lg border border-line p-3">
        <legend className="px-2 text-sm font-medium">{booking.booking_equipment.find((item) => item.equipment_id === loan.equipment_id)?.equipment?.name} · {loan.quantity} units</legend>
        <input type="hidden" name="equipment_id" value={loan.equipment_id} />
        <label className="label">Missing<input className="input" name={"missing:" + loan.equipment_id} type="number" min={0} max={loan.quantity} defaultValue={0} required /></label>
        <label className="label">Damaged<input className="input" name={"damaged:" + loan.equipment_id} type="number" min={0} max={loan.quantity} defaultValue={0} required /></label>
      </fieldset>)}</div>
      <label className="label">Return note <span className="sr-only">(optional)</span><textarea className="input" name="note" maxLength={1000} /></label>
    </Confirm></div> : hasEquipment ? pickupOpen ? <div><Confirm title="Record equipment pickup?" description="Record this only after handing the listed equipment to the requester."
      trigger="Record pickup" submit="Confirm pickup" action={recordPickup} booking={booking.id} /></div>
      : <p className="muted">{ended ? "The reservation period ended without a recorded pickup." : "Pickup opens at the start of the approved reservation window."}</p>
      : ended ? <div><Confirm title="Complete room usage?" description="Confirm that this reservation has finished."
        trigger="Complete booking" submit="Mark complete" action={completeRoom} booking={booking.id} /></div>
        : <p className="muted">The booking can be completed after its scheduled end.</p>}
  </section>;
}
