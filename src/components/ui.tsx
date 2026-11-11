import { AlertCircle, ArrowRight, CheckCircle2, ChevronRight, Circle, type LucideIcon } from "lucide-react";
import Link from "next/link";
import type { ActionState } from "@/lib/domain";

export function PageHeading({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) {
  return <div className="flex flex-wrap items-end justify-between gap-4"><div>
    <h1 className="page-title">{title}</h1>{description && <p className="muted mt-2">{description}</p>}
  </div>{action}</div>;
}

export function Notice({ children, tone = "info" }: { children: React.ReactNode; tone?: "info" | "error" | "success" }) {
  const Icon = tone === "success" ? CheckCircle2 : AlertCircle;
  const color = tone === "error" ? "border-[#623c46] bg-[#301d24] text-negative"
    : tone === "success" ? "border-[#315044] bg-[#152820] text-positive"
    : "border-[#2e4260] bg-[#132135] text-[#c4d7f5]";
  return <div className={"flex items-start gap-3 rounded-lg border px-4 py-3 text-sm leading-relaxed " + color} role={tone === "error" ? "alert" : "status"}>
    <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" /><div className="min-w-0">{children}</div>
  </div>;
}

export function Feedback({ state }: { state: ActionState }) {
  return state.error ? <Notice tone="error">{state.error}</Notice>
    : state.success ? <Notice tone="success">{state.success}</Notice> : null;
}

export function Status({ status }: { status: string }) {
  const color = ({
    approved: "bg-[#17332a] text-positive", available: "bg-[#17332a] text-positive", completed: "bg-[#193044] text-[#a5cef6]",
    pending: "bg-[#382c1b] text-warning", maintenance: "bg-[#382c1b] text-warning",
    rejected: "bg-[#3a252d] text-negative", reserved: "bg-[#3a252d] text-negative",
  } as Record<string, string>)[status] ?? "bg-raised text-muted";
  return <span className={"inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize " + color}>
    <Circle aria-hidden="true" className="size-1.5 fill-current" />{status}
  </span>;
}

export function Empty({ icon: Icon, title, description, href, action }: { icon: LucideIcon; title: string; description: string; href?: string; action?: string }) {
  return <div className="panel grid justify-items-center gap-3 px-5 py-10 text-center">
    <span className="rounded-lg border border-line bg-raised p-3 text-accent"><Icon className="size-5" aria-hidden="true" /></span>
    <h2 className="font-semibold">{title}</h2><p className="muted max-w-md">{description}</p>
    {href && <Link className="button button-secondary mt-1" href={href}>{action}<ArrowRight className="size-4" aria-hidden="true" /></Link>}
  </div>;
}

export function Breadcrumb({ href, label, current }: { href: string; label: string; current: string }) {
  return <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-2 text-sm text-muted">
    <Link href={href} className="hover:text-ink">{label}</Link><ChevronRight aria-hidden="true" className="size-4" /><span className="text-ink">{current}</span>
  </nav>;
}

export function Stat({ icon: Icon, label, count, detail }: { icon: LucideIcon; label: string; count: number | string; detail: string }) {
  return <div className="panel flex items-start gap-3 p-4 sm:gap-4 sm:p-5">
    <span className="grid size-10 shrink-0 place-items-center rounded-lg border border-[#304362] bg-[#18263b] text-accent"><Icon className="size-[18px]" aria-hidden="true" /></span>
    <div className="min-w-0"><div className="text-xs font-medium text-muted">{label}</div><div className="mt-1 text-[1.65rem] leading-tight font-semibold tracking-tight tabular-nums">{count}</div>
      <div className="mt-1.5 text-xs text-muted">{detail}</div></div>
  </div>;
}
