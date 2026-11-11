import { defaultLayout, type Equipment, type Room } from "./domain";

export const demoRooms: Room[] = [
  {
    id: "a1000000-0000-4000-8000-000000000001", name: "Seminar Room A", location: "Innovation Center · Level 2",
    description: "A tiered seminar space with a presentation wall and flexible seating for talks, panels, and committee events.",
    capacity: 80, chairs: 80, tables_count: 20, active: true, photo: "seminar",
    layout: { ...defaultLayout, width: 14, depth: 12, door: { ...defaultLayout.door, offset: 6.4 },
      fixed: [{ x: 0.5, y: 0.5, width: 0.6, depth: 0.6, label: "Lectern" }] },
    room_facilities: ["Projector", "Air conditioning", "Audio system", "Wi-Fi"].map((facility) => ({ facility })),
  },
  {
    id: "a1000000-0000-4000-8000-000000000002", name: "Classroom B", location: "Learning Commons · Level 1",
    description: "A bright, reconfigurable classroom with movable desks for workshops, training, and group discussions.",
    capacity: 36, chairs: 36, tables_count: 18, active: true, photo: "classroom", layout: defaultLayout,
    room_facilities: ["Whiteboard", "Air conditioning", "Wi-Fi"].map((facility) => ({ facility })),
  },
  {
    id: "a1000000-0000-4000-8000-000000000003", name: "Multipurpose Hall C", location: "Student Center · Ground floor",
    description: "An open hall for campus gatherings with an acoustic stage, wide aisles, and accessible entry.",
    capacity: 150, chairs: 150, tables_count: 35, active: true, photo: "hall",
    layout: { ...defaultLayout, width: 20, depth: 16, door: { ...defaultLayout.door, offset: 9.25, width: 1.5 },
      blocked: [{ x: 18, y: 0.5, width: 1.2, depth: 2, label: "Storage" }] },
    room_facilities: ["Audio system", "Projector", "Accessible entry"].map((facility) => ({ facility })),
  },
];

export const demoEquipment: Equipment[] = [
  ["Extension Cable", "Power", "A grounded 10-metre extension reel for event power.", 16],
  ["Wireless Microphone", "Audio", "Handheld wireless microphone with receiver and batteries.", 8],
  ["Portable Projector", "Presentation", "Compact HDMI projector with a protective carry case.", 5],
  ["Portable Speaker", "Audio", "Rechargeable speaker for small talks and workshops.", 6],
  ["Tripod", "Accessories", "Adjustable tripod with a standard quick-release plate.", 10],
  ["Folding Table", "Furniture", "Lightweight rectangular table for check-in or group activities.", 24],
  ["Stacking Chair", "Furniture", "Stackable seating for flexible campus events.", 80],
].map(([name, category, description, quantity], index) => ({
  id: "a2000000-0000-4000-8000-00000000000" + (index + 1),
  name: name as string, category: category as Equipment["category"], description: description as string,
  total_quantity: quantity as number, unusable_quantity: 0, status: "available",
}));
