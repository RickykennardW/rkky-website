import type { Period, Search } from "./domain";

export const campusTimeZone = "Asia/Jakarta";

export function dateInput(value = new Date()) {
  const parts = new Intl.DateTimeFormat("en-CA", { timeZone: campusTimeZone, year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(value);
  return ["year", "month", "day"].map((part) => parts.find(({ type }) => type === part)?.value).join("-");
}

export function nextDay(date: string, offset = 1) {
  const value = new Date(date + "T00:00:00Z");
  value.setUTCDate(value.getUTCDate() + offset);
  return value.toISOString().slice(0, 10);
}

export function toInstant(date: string, time: string) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) return null;
  const calendar = new Date(date + "T00:00:00Z");
  if (!Number.isFinite(calendar.getTime()) || calendar.toISOString().slice(0, 10) !== date) return null;
  return new Date(date + "T" + time + ":00+07:00").toISOString();
}

export function schedule(period: Period, requireFuture = false, now = new Date()) {
  const startsAt = toInstant(period.date, period.start);
  const endsAt = toInstant(period.endDate, period.end);
  return startsAt && endsAt && endsAt > startsAt && (!requireFuture || startsAt > now.toISOString())
    ? { startsAt, endsAt } : null;
}

export function value(search: Search, field: string) {
  const result = search[field];
  return typeof result === "string" ? result : "";
}

export function searchPeriod(search: Search): Period {
  const date = value(search, "date");
  return { date, endDate: value(search, "endDate") || date, start: value(search, "start"), end: value(search, "end") };
}

export function periodQuery(period: Period) {
  return period.date ? new URLSearchParams(period).toString() : "";
}

const day = new Intl.DateTimeFormat("en-GB", { timeZone: campusTimeZone, month: "short", day: "numeric", year: "numeric" });
const time = new Intl.DateTimeFormat("en-GB", { timeZone: campusTimeZone, hour: "2-digit", minute: "2-digit", hourCycle: "h23" });
export const formatDay = (iso: string) => day.format(new Date(iso));
export const formatTime = (iso: string) => time.format(new Date(iso));
export const formatStamp = (iso: string) => formatDay(iso) + " · " + formatTime(iso) + " WIB";
export function formatPeriod(start: string, end: string) {
  const sameDay = dateInput(new Date(start)) === dateInput(new Date(end));
  return formatDay(start) + " · " + formatTime(start) + " – " + (sameDay ? "" : formatDay(end) + " · ") + formatTime(end) + " WIB";
}
