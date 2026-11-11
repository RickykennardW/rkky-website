"use client";

import { useActionState } from "react";
import { ArrowRight, LoaderCircle } from "lucide-react";
import { login, signup } from "@/lib/actions";
import { Feedback } from "./ui";

export function AuthForm({ registration, configured }: { registration: boolean; configured: boolean }) {
  const [state, action, pending] = useActionState(registration ? signup : login, {});
  return <form action={action} className="grid gap-4">
    {registration && <label className="label">Full name<input name="fullName" autoComplete="name" className="input" required minLength={2} maxLength={100} placeholder="Your name" /></label>}
    <label className="label">Email<input name="email" type="email" autoComplete="email" className="input" placeholder="you@campus.edu" required maxLength={254} /></label>
    <label className="label">Password<input name="password" type="password" autoComplete={registration ? "new-password" : "current-password"} className="input" required minLength={registration ? 8 : 1} maxLength={72} placeholder={registration ? "At least eight characters" : "Your password"} /></label>
    <Feedback state={state} />
    <button className="button button-primary mt-1" type="submit" disabled={pending || !configured}>
      {pending ? <LoaderCircle className="size-4 motion-safe:animate-spin" aria-hidden="true" /> : null}
      {registration ? "Create account" : "Sign in"}<ArrowRight className="size-4" aria-hidden="true" />
    </button>
  </form>;
}
