"use client";

import { useRef } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { ArrowUpRight, Boxes, Building2, CalendarCheck, CalendarDays, CalendarPlus, CircleHelp, Grid2X2, LayoutDashboard, LogOut, Menu, PackageCheck, PanelTop, Settings2, ShieldCheck, X } from "lucide-react";
import { logout } from "@/lib/actions";
import type { Profile } from "@/lib/domain";

const student = [
  { href: "/", label: "Dashboard", icon: LayoutDashboard },
  { href: "/rooms", label: "Rooms", icon: Building2 },
  { href: "/equipment", label: "Equipment", icon: Boxes },
  { href: "/bookings", label: "My Bookings", icon: CalendarDays },
  { href: "/planner", label: "Room Planner", icon: Grid2X2 },
];
const admin = [
  { href: "/admin", label: "Admin Overview", icon: ShieldCheck },
  { href: "/admin/bookings", label: "Booking Requests", icon: CalendarCheck },
  { href: "/admin/rooms", label: "Manage Rooms", icon: Building2 },
  { href: "/admin/equipment", label: "Manage Equipment", icon: Settings2 },
  { href: "/admin/loans", label: "Equipment Loans", icon: PackageCheck },
];

export function Brand() {
  return <Link className="inline-flex items-center gap-3" href="/" aria-label="CampusSpace dashboard">
    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand text-[#0b1628]"><PanelTop strokeWidth={2.4} className="size-5" aria-hidden="true" /></span>
    <span className="text-[1.05rem] font-semibold tracking-tight">CampusSpace</span>
  </Link>;
}

function Sidebar({ profile, preview, close }: { profile: Profile | null; preview: boolean; close?: () => void }) {
  const path = usePathname();
  function links(items: typeof student) {
    return items.map(({ href, label, icon: Icon }) => {
      const active = href === "/" || href === "/admin" ? path === href : path === href || path.startsWith(href + "/");
      return <Link key={href} href={href} onClick={close} aria-current={active ? "page" : undefined}
        className={"flex min-h-11 items-center gap-3 rounded-lg px-3 text-[0.84rem] font-medium transition-colors " + (active ? "bg-[#1b2c45] text-[#aec8fc]" : "text-muted hover:bg-raised hover:text-ink")}>
        <Icon className="size-[17px]" aria-hidden="true" />{label}
      </Link>;
    });
  }
  const initials = profile?.full_name.split(/\s+/).slice(0, 2).map((word) => word[0]).join("").toUpperCase();
  return <div className="flex h-full flex-col">
    <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-5"><Brand />
      {close && <button className="button button-ghost px-2" type="button" aria-label="Close navigation" onClick={close}><X className="size-5" /></button>}
    </div>
    <nav aria-label="Workspace" className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-3 py-5">
      <div className="grid gap-1"><p className="mb-1 px-3 text-[0.65rem] font-semibold tracking-[0.16em] text-muted uppercase">Workspace</p>{links(student)}</div>
      {profile?.role === "admin" && <div className="grid gap-1"><p className="mb-1 px-3 text-[0.65rem] font-semibold tracking-[0.16em] text-muted uppercase">Administration</p>{links(admin)}</div>}
    </nav>
    <div className="grid gap-3 border-t border-line p-3">
      {preview && <Link href="/setup" onClick={close} className="flex min-h-10 items-center gap-2.5 rounded-lg px-3 text-sm text-muted hover:bg-raised hover:text-ink">
        <CircleHelp className="size-4" /> Setup guide <ArrowUpRight className="ml-auto size-3.5" />
      </Link>}
      {profile ? <div className="flex items-center gap-2.5 rounded-lg bg-raised p-2.5">
        <div className="grid size-8 shrink-0 place-items-center rounded-full bg-[#243755] text-xs font-semibold text-[#c3d6fb]" aria-hidden="true">{initials}</div>
        <div className="min-w-0 flex-1"><div className="truncate text-xs font-medium">{profile.full_name}</div><div className="mt-0.5 text-xs text-muted capitalize">{profile.role}</div></div>
        <form action={logout}><button type="submit" aria-label="Sign out" title="Sign out" className="rounded p-2 text-muted hover:bg-[#283748] hover:text-ink"><LogOut className="size-4" /></button></form>
      </div> : <Link href="/login" onClick={close} className="button button-secondary w-full">Sign in <ArrowUpRight className="size-4" /></Link>}
    </div>
  </div>;
}

export function Shell({ children, profile, preview }: { children: React.ReactNode; profile: Profile | null; preview: boolean }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const path = usePathname();
  const active = [...student, ...admin].sort((a, b) => b.href.length - a.href.length).find(({ href }) => path === href || path.startsWith(href + "/"));
  const section = active?.label ?? (path.startsWith("/book") ? "New booking" : "Workspace");
  return <div className="min-h-dvh">
    <a href="#main" className="fixed top-3 left-3 z-50 -translate-y-[150%] rounded bg-brand px-4 py-2 text-canvas focus:translate-y-0">Skip to content</a>
    <aside className="fixed inset-y-0 left-0 z-30 hidden w-[226px] border-r border-line bg-[#10161e] lg:block">
      <Sidebar profile={profile} preview={preview} />
    </aside>
    <dialog ref={dialog} aria-label="Navigation" className="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-[min(90vw,320px)] max-w-none border-r border-line bg-[#10161e] text-ink backdrop:bg-black/70">
      <Sidebar profile={profile} preview={preview} close={() => dialog.current?.close()} />
    </dialog>
    <div className="lg:pl-[226px]">
      <header className="sticky top-0 z-20 border-b border-line bg-canvas">
        <div className="mx-auto flex min-h-[68px] max-w-[1470px] items-center justify-between gap-3 px-4 sm:px-7 xl:px-9">
          <div className="flex min-w-0 items-center gap-3">
            <button type="button" onClick={() => dialog.current?.showModal()} className="button button-ghost px-2 lg:hidden" aria-label="Open navigation"><Menu className="size-5" /></button>
            <span className="hidden text-sm text-muted sm:inline">CampusSpace</span><span aria-hidden="true" className="hidden text-[#526173] sm:inline">/</span>
            <span className="truncate text-sm font-medium">{section}</span>
          </div>
          <Link href={preview ? "/setup" : "/book"} className="button button-primary"><CalendarPlus className="size-4" aria-hidden="true" /> New booking</Link>
        </div>
      </header>
      <main id="main" className="mx-auto grid max-w-[1470px] gap-7 px-4 py-6 sm:px-7 sm:py-8 xl:px-9">{children}</main>
    </div>
  </div>;
}
