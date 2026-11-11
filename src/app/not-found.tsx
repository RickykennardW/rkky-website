import Link from "next/link";
import { ArrowLeft, Compass } from "lucide-react";

export default function NotFound() {
  return <main className="grid min-h-dvh place-items-center p-5"><section className="panel grid max-w-md gap-4 p-7">
    <Compass className="text-accent" aria-hidden="true" /><h1 className="page-title">Page unavailable</h1>
    <p className="muted">This resource may have moved or require a different account.</p>
    <Link className="button button-primary justify-self-start" href="/"><ArrowLeft className="size-4" /> Dashboard</Link>
  </section></main>;
}
