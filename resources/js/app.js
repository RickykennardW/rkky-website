import Alpine from "alpinejs";
import {
  createIcons, Armchair, ArrowLeft, ArrowRight, ArrowUpRight, AudioLines, Bell, Boxes,
  Building2, Cable, CalendarCheck, CalendarDays, CalendarPlus, CalendarRange, ChevronLeft,
  ChevronRight, CircleAlert, CircleCheck, Clock3, Grid2X2, LayoutDashboard, LogOut, MapPin,
  Menu, Package, PackageCheck, PanelTop, Pencil, Plus, Presentation, Search, Settings2,
  ShieldCheck, Table2, Users, X,
} from "lucide";
import { bookingForm } from "./booking-form";
import { roomPlanner } from "./room-planner";
import { roomMonitoring } from "./room-monitoring";

Alpine.data("bookingForm", bookingForm);
Alpine.data("roomPlanner", roomPlanner);
Alpine.data("roomMonitoring", roomMonitoring);
Alpine.start();

createIcons({
  icons: {
    Armchair, ArrowLeft, ArrowRight, ArrowUpRight, AudioLines, Bell, Boxes, Building2,
    Cable, CalendarCheck, CalendarDays, CalendarPlus, CalendarRange, ChevronLeft,
    ChevronRight, CircleAlert, CircleCheck, Clock3, Grid2x2: Grid2X2, LayoutDashboard, LogOut,
    MapPin, Menu, Package, PackageCheck, PanelTop, Pencil, Plus, Presentation, Search,
    Settings2, ShieldCheck, Table2, Users, X,
  },
});

document.addEventListener("submit", (event) => {
  const confirmation = event.target.dataset?.confirm;
  if (confirmation && !window.confirm(confirmation)) {
    event.preventDefault();
  }
});
