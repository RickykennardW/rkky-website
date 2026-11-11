import Image from "next/image";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { redirect } from "next/navigation";
import { AuthForm } from "./auth-form";
import { Notice } from "./ui";
import { Brand } from "./shell";
import { getIdentity } from "@/lib/data";

export async function AuthScreen({ registration = false, failed = false }: { registration?: boolean; failed?: boolean }) {
  const identity = await getIdentity();
  if (identity.profile) redirect("/");
  return <main className="grid min-h-dvh lg:grid-cols-[1fr_0.9fr]">
    <div className="mx-auto flex w-full max-w-xl flex-col gap-9 px-5 py-7 sm:px-12 sm:py-10">
      <div className="flex flex-wrap items-center justify-between gap-4"><Brand />{identity.preview && <Link href="/" className="flex items-center gap-2 text-sm text-muted hover:text-ink"><ArrowLeft className="size-4" /> Dashboard</Link>}</div>
      <section className="panel my-auto grid gap-5 p-5 sm:p-8">
        <div><h1 className="page-title">{registration ? "Create your account" : "Sign in"}</h1>
          <p className="muted mt-2">{registration ? "Use your campus email to book rooms and borrow equipment." : "Access your campus bookings and equipment loans."}</p></div>
        {identity.preview && <Notice>Supabase is required to use authentication. <Link href="/setup" className="underline underline-offset-4">View setup steps</Link></Notice>}
        {failed && <Notice tone="error">The confirmation link could not be verified. Try signing in or request a new link by registering again.</Notice>}
        <AuthForm registration={registration} configured={!identity.preview} />
        <p className="muted">{registration ? "Already registered?" : "New to CampusSpace?"}{" "}
          <Link className="font-semibold text-accent hover:underline" href={registration ? "/login" : "/register"}>{registration ? "Sign in" : "Create an account"}</Link></p>
      </section>
    </div>
    <div className="relative hidden overflow-hidden border-l border-line lg:block">
      <Image src="/rooms/seminar.png" alt="Illustration of a modern campus seminar room" fill priority sizes="48vw" className="object-cover object-center" />
      <div aria-hidden="true" className="absolute inset-0 bg-linear-to-t from-[#0b0f14] via-[#0b0f14]/15 to-transparent" />
    </div>
  </main>;
}
