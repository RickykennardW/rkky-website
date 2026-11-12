<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipmentRequest;
use App\Http\Requests\RoomRequest;
use App\Models\Booking;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\Room;
use App\Services\Inventory;
use App\Services\RoomGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $pending = Booking::query()->where('status', 'pending');
        $upcoming = Booking::query()->whereNotNull('room_id')->whereIn('status', ['approved', 'completed'])->where('ends_at', '>', now());

        return view('admin.dashboard', [
            'pendingCount' => (clone $pending)->count(),
            'approvedCount' => Booking::query()->where('status', 'approved')->count(),
            'activeLoans' => EquipmentLoan::query()->whereNull('returned_at')->count(),
            'losses' => EquipmentLoan::query()->where('allocation_warning', true)->count(),
            'activeRooms' => Room::query()->where('active', true)->count(),
            'roomCount' => Room::query()->count(),
            'equipmentCount' => Equipment::query()->count(),
            'usable' => Equipment::query()->where('status', 'available')->sum(DB::raw('total_quantity - unusable_quantity')),
            'pending' => $pending->with(['profile', 'room'])->orderBy('starts_at')->orderBy('id')->limit(5)->get(),
            'upcoming' => $upcoming->with(['profile', 'room'])->orderBy('starts_at')->orderBy('id')->limit(5)->get(),
        ]);
    }

    public function rooms(): View
    {
        return view('admin.rooms', [
            'rooms' => Room::query()->orderBy('name')->orderBy('id')->paginate(12),
        ]);
    }

    public function roomEditor(?Room $room = null): View
    {
        return view('admin.room-editor', [
            'room' => $room?->load('facilities'),
            'layout' => $room?->layout ?? [],
            'facilities' => Room::FACILITIES,
        ]);
    }

    public function saveRoom(RoomRequest $request, Inventory $inventory, ?Room $room = null): RedirectResponse
    {
        $data = $request->validated();
        $data['layout'] = RoomGeometry::normalize($data['layout']);
        $inventory->saveRoom($request->user(), $room, $data);

        return redirect()->route('admin.rooms.index')->with('status', 'Room saved.');
    }

    public function equipment(): View
    {
        return view('admin.equipment', [
            'equipment' => Equipment::query()->orderBy('name')->orderBy('id')->paginate(12),
        ]);
    }

    public function equipmentEditor(?Equipment $equipment = null): View
    {
        return view('admin.equipment-editor', [
            'equipment' => $equipment,
            'categories' => Equipment::CATEGORIES,
            'statuses' => Equipment::STATUSES,
        ]);
    }

    public function saveEquipment(EquipmentRequest $request, Inventory $inventory, ?Equipment $equipment = null): RedirectResponse
    {
        $inventory->saveEquipment($request->user(), $equipment, $request->validated());

        return redirect()->route('admin.equipment.index')->with('status', 'Equipment saved.');
    }

    public function loans(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:active,overdue,returned']])['status'] ?? null;
        $query = EquipmentLoan::query()->with(['equipment', 'booking.profile'])
            ->orderByDesc('picked_up_at')->orderBy('booking_id')->orderBy('equipment_id');

        match ($status) {
            'active' => $query->whereNull('returned_at'),
            'overdue' => $query->whereNull('returned_at')->whereHas('booking', fn ($bookings) => $bookings->where('ends_at', '<=', now())),
            'returned' => $query->whereNotNull('returned_at'),
            default => null,
        };

        return view('admin.loans', ['loans' => $query->paginate(15)->withQueryString(), 'status' => $status]);
    }
}
