"use client";

import { AlertCircle, RotateCw } from "lucide-react";

export default function WorkspaceError({ reset }: { error: Error; reset: () => void }) {
  return <section className="panel grid justify-items-start gap-4 p-6">
    <AlertCircle aria-hidden="true" className="text-warning" /><h2 className="section-title">CampusSpace could not load this page</h2>
    <p className="muted">Check your connection and try again. If this is a new installation, verify the Supabase settings and migrations.</p>
    <button className="button button-primary" type="button" onClick={reset}><RotateCw className="size-4" /> Retry</button>
  </section>;
}
