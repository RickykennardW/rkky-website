import { z } from "zod";
import { categories, equipmentStatuses, facilities } from "./domain";
import { toInstant } from "./time";

const number = (min: number, max: number) =>
  z.preprocess((input) => typeof input === "string" && input.trim() !== "" ? Number(input) : input, z.number().int().min(min).max(max));
const uuid = z.uuid();
const text = (min: number, max: number) => z.string().trim().min(min).max(max);
const optionalId = z.preprocess((value) => value === "" || value === null ? undefined : value, uuid.optional());

export const loginSchema = z.object({ email: z.email().max(254), password: z.string().min(1).max(72) });
export const signupSchema = loginSchema.extend({
  fullName: text(2, 100),
  password: z.string().min(8, "Use at least eight characters.").refine((password) => new TextEncoder().encode(password).length <= 72, "Use at most 72 bytes for your password."),
});
export const bookingSchema = z.object({
  roomId: optionalId, title: text(2, 120), description: text(2, 2000),
  participants: number(1, 500), committee: number(0, 500),
  date: z.string(), endDate: z.string(), start: z.string(), end: z.string(),
  items: z.array(z.object({ equipment_id: uuid, quantity: number(1, 10000) })).max(100),
}).refine(({ roomId, items }) => roomId || items.length, "Choose a room or at least one equipment item.")
  .refine(({ items }) => new Set(items.map(({ equipment_id }) => equipment_id)).size === items.length, "Equipment selections must be unique.")
  .refine(({ date, endDate, start, end }) => {
    const begins = toInstant(date, start);
    const finishes = toInstant(endDate, end);
    return !!begins && !!finishes && finishes > begins && begins > new Date().toISOString();
  }, "Choose a future start and an end after it.");

const zoneSchema = z.object({ x: z.number().min(0), y: z.number().min(0), width: z.number().positive(), depth: z.number().positive(), label: text(1, 60) });
export const layoutSchema = z.object({
  width: z.number().min(3).max(60), depth: z.number().min(3).max(60),
  door: z.object({ wall: z.enum(["north", "south", "east", "west"]), offset: z.number().min(0), width: z.number().min(0.8).max(5), clearance: z.number().min(0.9).max(5) }),
  blocked: z.array(zoneSchema).max(25), fixed: z.array(zoneSchema).max(25),
}).refine((layout) => layout.door.offset + layout.door.width <= (["north", "south"].includes(layout.door.wall) ? layout.width : layout.depth), "The door must fit on the selected wall.")
  .refine(({ width, depth, blocked, fixed }) => [...blocked, ...fixed].every((zone) => zone.x + zone.width <= width && zone.y + zone.depth <= depth), "Every zone must fit inside the room.");

export const roomSchema = z.object({
  id: optionalId, name: text(2, 100), location: text(2, 120), description: text(1, 1500),
  capacity: number(1, 500), chairs: number(0, 500), tables: number(0, 500), active: z.boolean(),
  photo: z.enum(["", "seminar", "classroom", "hall"]), layout: layoutSchema,
  facilities: z.array(z.enum(facilities)),
});

export const equipmentSchema = z.object({
  id: optionalId, name: text(2, 100), category: z.enum(categories), description: text(1, 1500),
  total: number(0, 10000), unusable: number(0, 10000), status: z.enum(equipmentStatuses),
}).refine(({ total, unusable }) => unusable <= total, "Unusable units cannot exceed total stock.");

export const returnSchema = z.object({
  note: z.string().trim().max(1000),
  items: z.array(z.object({ equipment_id: uuid, missing: number(0, 10000), damaged: number(0, 10000) })).min(1).max(100),
});
