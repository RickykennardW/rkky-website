import Link from "next/link";
import { ArrowLeft, Database } from "lucide-react";
import { Brand } from "@/components/shell";
import { supabaseSettings } from "@/lib/supabase-config";

export const metadata = { title: "Supabase setup" };
export const dynamic = "force-dynamic";

export default function Setup() {
  return <main className="mx-auto grid min-h-dvh max-w-3xl content-start gap-7 px-5 py-8 sm:py-12">
    <Brand />
    <section className="panel grid gap-5 p-6 sm:p-9">
      <Database className="size-7 text-accent" aria-hidden="true" />
      <h1 className="page-title">{supabaseSettings() ? "CampusSpace setup" : "Connect Supabase to get started"}</h1>
      <p className="muted">Reservations and campus administration require a configured Supabase project. The preview catalog uses fictional resources.</p>
      <ol className="grid list-decimal gap-3 pl-5 text-sm leading-relaxed text-[#d5dfeb]">
        <li>Apply the CampusSpace migration in your Supabase project.</li>
        <li>Set <code className="break-all text-accent">NEXT_PUBLIC_SUPABASE_URL</code> and <code className="break-all text-accent">NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY</code> in your local environment.</li>
        <li>Configure the Auth callback, load the optional sample catalog, and assign your first admin through the SQL editor. The project README contains the commands.</li>
        <li>Restart the development server and sign in.</li>
      </ol>
      <Link href={supabaseSettings() ? "/login" : "/"} className="button button-secondary justify-self-start"><ArrowLeft className="size-4" />{supabaseSettings() ? "Sign in" : "Back to catalog preview"}</Link>
    </section>
  </main>;
}
