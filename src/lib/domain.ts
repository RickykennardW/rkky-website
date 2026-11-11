export const facilities = ["Projector", "Air conditioning", "Whiteboard", "Audio system", "Wi-Fi", "Accessible entry"] as const;
export const categories = ["Audio", "Presentation", "Furniture", "Power", "Accessories"] as const;
export const bookingStatuses = ["pending", "approved", "rejected", "cancelled", "completed"] as const;
export const equipmentStatuses = ["available", "maintenance", "inactive"] as const;

export type BookingStatus = typeof bookingStatuses[number];
export type EquipmentStatus = typeof equipmentStatuses[number];
export type Photo = "seminar" | "classroom" | "hall";
export type Rect = { x: number; y: number; width: number; depth: number };
export type LayoutZone = Rect & { label: string };
export type RoomLayout = {
  width: number;
  depth: number;
  door: { wall: "north" | "south" | "east" | "west"; offset: number; width: number; clearance: number };
  blocked: LayoutZone[];
  fixed: LayoutZone[];
};
export type Room = {
  id: string; name: string; location: string; description: string; capacity: number;
  chairs: number; tables_count: number; active: boolean; photo: Photo | null;
  layout: RoomLayout;
  room_facilities: { facility: string }[];
};
export type Equipment = {
  id: string; name: string; category: typeof categories[number]; description: string;
  total_quantity: number; unusable_quantity: number; status: EquipmentStatus;
};
export type Profile = { id: string; full_name: string; email: string; role: "student" | "admin" };
export type EquipmentLoan = {
  equipment_id: string; quantity: number; picked_up_at: string; returned_at: string | null;
  missing: number; damaged: number; return_note: string | null; allocation_warning: boolean;
};
export type Booking = {
  id: string; user_id: string; room_id: string | null; title: string; description: string;
  participants: number; committee: number; starts_at: string; ends_at: string;
  status: BookingStatus; planner: unknown; review_note: string | null; created_at: string;
  reviewed_at: string | null;
  rooms: { name: string; location: string } | null;
  profiles: { full_name: string; email: string } | null;
  booking_equipment: { equipment_id: string; quantity: number; equipment: { name: string } | null }[];
  equipment_loans: EquipmentLoan[];
};
export type Availability = { rooms: Record<string, boolean>; equipment: Record<string, number> };
export type Period = { date: string; endDate: string; start: string; end: string };
export type Search = Record<string, string | string[] | undefined>;
export type ActionState = { error?: string; success?: string };

export const defaultLayout: RoomLayout = {
  width: 12, depth: 10,
  door: { wall: "south", offset: 5.4, width: 1.2, clearance: 1.2 },
  blocked: [], fixed: [],
};
