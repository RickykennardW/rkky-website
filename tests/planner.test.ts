import { describe, expect, test } from "vitest";
import { demoRooms } from "../src/lib/demo";
import { estimate, generatePlan, layouts, overlaps, type PlannerInput } from "../src/lib/planner";
import { schedule, toInstant } from "../src/lib/time";

const event: PlannerInput = { eventType: "Seminar", participants: 12, committee: 4, presentation: true, registrationDesk: true, discussion: false };

describe("Room planner", () => {
  test("estimates seating for participants and half the committee, with a check-in table", () => {
    expect(estimate(event)).toMatchObject({ chairs: 14, tables: 1, microphones: 1, projectors: 1, cables: 2, arrangement: "Theater" });
    expect(estimate({ ...event, eventType: "Workshop" })).toMatchObject({ arrangement: "Group / Cluster", tables: 5 });
  });
  test.each(layouts)("places %s furniture without collisions and leaves access clear", (arrangement) => {
    const room = demoRooms[0];
    const plan = { ...estimate(event), arrangement, tables: arrangement === "Theater" ? 1 : arrangement === "Group / Cluster" ? 5 : 8 };
    const result = generatePlan(room, plan);
    expect(result.error).toBeUndefined();
    expect(result.items.filter(({ kind }) => kind === "chair")).toHaveLength(14);
    expect(result.items.filter(({ kind }) => kind === "table" || kind === "registration")).toHaveLength(plan.tables);
    for (const [index, item] of result.items.entries()) {
      if (["clearance", "fixed", "blocked"].includes(item.kind)) continue;
      expect(item.x).toBeGreaterThanOrEqual(0);
      expect(item.y).toBeGreaterThanOrEqual(0);
      expect(item.x + item.width).toBeLessThanOrEqual(room.layout.width);
      expect(item.y + item.depth).toBeLessThanOrEqual(room.layout.depth);
      expect(result.items.some((other, i) => index !== i && overlaps(item, other))).toBe(false);
    }
  });
  test("reports capacity, furniture and blocked access failures", () => {
    const room = demoRooms[0];
    expect(generatePlan({ ...room, capacity: 15 }, estimate(event)).error).toContain("capacity");
    expect(generatePlan({ ...room, chairs: 5 }, estimate(event)).error).toContain("inventory");
    const blocked = { ...room, layout: { ...room.layout, blocked: [{ x: 6.4, y: 11, width: 1.2, depth: 1, label: "Door obstruction" }] } };
    expect(generatePlan(blocked, estimate(event)).error).toContain("intersects");
  });
});

describe("Jakarta reservation times", () => {
  test("validates the calendar and converts local times to UTC", () => {
    expect(toInstant("2026-11-12", "09:30")).toBe("2026-11-12T02:30:00.000Z");
    expect(toInstant("2026-02-30", "09:30")).toBeNull();
    expect(toInstant("2026-11-12", "24:30")).toBeNull();
    expect(schedule({ date: "2026-11-12", endDate: "2026-11-12", start: "10:00", end: "09:00" })).toBeNull();
  });
});
