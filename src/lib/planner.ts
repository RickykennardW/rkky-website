import { z } from "zod";
import type { Rect, Room } from "./domain";
import { layoutSchema } from "./validation";

export const eventTypes = ["Seminar", "Meeting", "Workshop", "Presentation", "General Event"] as const;
export const layouts = ["Theater", "Classroom", "U-Shape", "Group / Cluster"] as const;
export const plannerRules = { committeeSeatShare: 0.5, workshopGroup: 4, classroomSeatsPerTable: 2, largeEventThreshold: 50 };
export const plannerInputSchema = z.object({
  eventType: z.enum(eventTypes), participants: z.number().int().min(1).max(500),
  committee: z.number().int().min(0).max(500),
  presentation: z.boolean(), registrationDesk: z.boolean(), discussion: z.boolean(),
});
export const planSchema = plannerInputSchema.extend({
  arrangement: z.enum(layouts), chairs: z.number().int().min(0).max(500), tables: z.number().int().min(0).max(500),
  microphones: z.number().int().min(0).max(500), projectors: z.number().int().min(0).max(500), cables: z.number().int().min(0).max(500),
});
export type PlannerInput = z.infer<typeof plannerInputSchema>;
export type Plan = z.infer<typeof planSchema>;
export type LayoutItem = Rect & { kind: "chair" | "table" | "stage" | "registration" | "blocked" | "fixed" | "clearance"; label?: string };

export function estimate(input: PlannerInput, rules = plannerRules): Plan {
  const chairs = input.participants + Math.ceil(input.committee * rules.committeeSeatShare);
  const arrangement = input.discussion || input.eventType === "Workshop" ? "Group / Cluster"
    : input.eventType === "Meeting" ? "U-Shape" : "Theater";
  const tableSeats = arrangement === "Group / Cluster" ? rules.workshopGroup : rules.classroomSeatsPerTable;
  return {
    ...input, arrangement, chairs,
    tables: (arrangement === "Theater" ? 0 : Math.ceil(chairs / tableSeats)) + Number(input.registrationDesk),
    microphones: input.presentation ? (input.participants >= rules.largeEventThreshold ? 2 : 1) : 0,
    projectors: Number(input.presentation), cables: Number(input.presentation) + Number(input.registrationDesk),
  };
}

export function overlaps(a: Rect, b: Rect) {
  const tolerance = 0.00001;
  return a.x < b.x + b.width - tolerance && a.x + a.width > b.x + tolerance &&
    a.y < b.y + b.depth - tolerance && a.y + a.depth > b.y + tolerance;
}

function candidates(from: number, to: number, step: number) {
  const positions: number[] = [];
  for (let position = from; position <= to; position += step) positions.push(Math.round(position * 100) / 100);
  return positions;
}

export function generatePlan(room: Room, plan: Plan): { items: LayoutItem[]; error?: string } {
  const geometry = layoutSchema.safeParse(room.layout);
  if (!geometry.success) return { items: [], error: "The room geometry needs to be corrected by a campus administrator." };
  const { width, depth, door, blocked, fixed } = geometry.data;
  const obstacles: LayoutItem[] = [...blocked.map((zone) => ({ ...zone, kind: "blocked" as const })), ...fixed.map((zone) => ({ ...zone, kind: "fixed" as const }))];
  const horizontalDoor = door.wall === "north" || door.wall === "south";
  const entry: Rect = horizontalDoor
    ? { x: door.offset, y: door.wall === "north" ? 0 : depth - door.clearance, width: door.width, depth: door.clearance }
    : { x: door.wall === "west" ? 0 : width - door.clearance, y: door.offset, width: door.clearance, depth: door.width };
  const aisle: Rect = { x: width / 2 - 0.45, y: 0.25, width: 0.9, depth: depth - 0.5 };
  const corridor: Rect = {
    x: Math.min(entry.x + entry.width / 2 - 0.45, aisle.x),
    y: Math.max(0, Math.min(depth - 0.9, entry.y + entry.depth / 2 - 0.45)),
    width: Math.abs(entry.x + entry.width / 2 - 0.45 - aisle.x) + 0.9, depth: 0.9,
  };
  corridor.width = Math.min(corridor.width + Math.min(corridor.x, 0), width - Math.max(corridor.x, 0));
  corridor.x = Math.max(0, corridor.x);
  const clearances: LayoutItem[] = [entry, aisle, corridor].map((area) => ({ ...area, kind: "clearance" }));
  const base = [...obstacles, ...clearances];
  const invalid = (error: string) => ({ items: base, error });
  if (plan.participants + plan.committee > room.capacity) return invalid("Total attendance exceeds this room’s capacity.");
  if (plan.chairs > room.chairs || plan.tables > room.tables_count) return invalid("This plan exceeds the room’s furniture inventory. Reduce the quantities or choose another room.");
  if (door.clearance > (horizontalDoor ? depth : width) || clearances.some((area) => obstacles.some((zone) => overlaps(area, zone)))) {
    return invalid("The configured door clearance or aisle intersects a blocked zone or fixed equipment.");
  }
  if (plan.registrationDesk && plan.tables < 1) return invalid("Add one table for the registration desk.");

  const items = [...base];
  const fits = (objects: LayoutItem[]) => objects.every((object) => object.x >= 0.2 && object.y >= 0.2 &&
    object.x + object.width <= width - 0.2 && object.y + object.depth <= depth - 0.2 &&
    !items.some((existing) => overlaps(object, existing)));
  function place(kind: LayoutItem["kind"], itemWidth: number, itemDepth: number, nearBack = false) {
    const rows = candidates(0.35, depth - itemDepth - 0.2, 0.45);
    if (nearBack) rows.reverse();
    for (const y of rows) {
      for (const x of candidates(0.35, width - itemWidth - 0.2, 0.45)) {
        const item = { x, y, width: itemWidth, depth: itemDepth, kind };
        if (fits([item])) { items.push(item); return true; }
      }
    }
    return false;
  }
  if (plan.presentation && !place("stage", Math.min(3, width / 2 - 0.7), 0.9)) return invalid("There is no clear space for a presentation area.");
  if (plan.registrationDesk && !place("registration", 1.4, 0.65, door.wall !== "north")) return invalid("The registration desk cannot fit without blocking access.");

  let seats = plan.chairs;
  let tables = plan.tables - Number(plan.registrationDesk);
  const placeBundle = (x: number, y: number, size: number, vertical = false, mirrored = false) => {
    const group = plan.arrangement === "Group / Cluster";
    const table: LayoutItem = { x: x + (vertical && !mirrored ? 0.56 : 0), y: y + (group ? 0.54 : 0), width: vertical ? 0.62 : 1.35, depth: vertical ? 1.35 : 0.64, kind: "table" };
    const chairs = Array.from({ length: Math.min(seats, size) }, (_, index): LayoutItem => {
      const position = index % 2 * 0.67 + 0.08;
      return {
        x: vertical ? x + (mirrored ? 0.74 : 0) : x + position,
        y: vertical ? y + position : group && index < 2 ? y : y + (group ? 1.31 : 0.78),
        width: 0.45, depth: 0.45, kind: "chair",
      };
    });
    if (!fits([table, ...chairs])) return;
    items.push(table, ...chairs);
    seats -= chairs.length;
    tables -= 1;
  };
  const startY = plan.presentation ? 1.6 : 0.45;
  if (plan.arrangement === "U-Shape") {
    for (const x of candidates(0.5, width - 1.6, 1.65)) {
      if (tables && seats) placeBundle(x, startY, 2);
    }
    for (const y of candidates(startY + 1.55, depth - 1.55, 1.65)) {
      if (tables && seats) placeBundle(0.45, y, 2, true);
      if (tables && seats) placeBundle(width - 1.65, y, 2, true, true);
    }
  } else if (plan.arrangement !== "Theater") {
    const group = plan.arrangement === "Group / Cluster";
    for (const y of candidates(startY, depth - 0.85, group ? 2.1 : 1.55)) {
      for (const x of candidates(0.45, width - 1.5, group ? 1.95 : 1.65)) {
        if (tables && seats) placeBundle(x, y, group ? 4 : 2);
      }
    }
  }
  if (plan.arrangement === "Theater") {
    for (const y of candidates(startY, depth - 0.7, 0.85)) {
      for (const x of candidates(0.45, width - 0.7, 0.65)) {
        const chair = { x, y, width: 0.45, depth: 0.45, kind: "chair" as const };
        if (seats && fits([chair])) { items.push(chair); seats -= 1; }
      }
    }
  }
  if (seats) return invalid("This arrangement could not place " + seats + " of the requested seats while keeping doors, aisles and blocked zones clear. Adjust the layout, tables or seating count.");
  while (tables > 0) {
    if (!place("table", 1.35, 0.65, true)) return invalid("There is insufficient clear space for all requested tables.");
    tables -= 1;
  }
  return { items };
}
