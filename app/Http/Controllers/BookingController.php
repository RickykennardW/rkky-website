<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnEquipmentRequest;
use App\Http\Requests\ReviewBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Room;
use App\Services\BookingPeriod;
use App\Services\Inventory;
use App\Services\Reservations;
use App\Services\SmartRoomPlanner;
use App\Services\SmartScheduling;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly Reservations $reservations,
        private readonly Inventory $inventory,
        private readonly SmartRoomPlanner $planner,
    ) {}

    public function index(Request $request): View
    {
        return $this->listing($request, Booking::query()->ownedBy($request->user()), false);
    }

    public function adminIndex(Request $request): View
    {
        return $this->listing($request, Booking::query(), true);
    }

    public function create(Request $request): View
    {
        if ($request->filled('endDate') && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('endDate')]);
        }
        $data = $request->validate([
            'room' => ['nullable', 'uuid'],
            'equipment' => ['nullable', 'uuid'],
            'plan' => ['nullable', 'string', 'max:16000'],
            ...BookingPeriod::rules(false),
            ...SmartScheduling::inputRules(false),
        ]);

        $rooms = Room::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'capacity']);
        $selectedRoom = $rooms->firstWhere('id', $data['room'] ?? null);
        $plan = $selectedRoom && isset($data['plan']) ? $this->planner->decode($data['plan']) : null;

        return view('bookings.create', [
            'rooms' => $rooms,
            'equipment' => Equipment::query()->orderBy('name')->get(),
            'selectedRoom' => $selectedRoom,
            'selectedEquipment' => $data['equipment'] ?? null,
            'period' => array_replace(BookingPeriod::defaults(), array_intersect_key(array_filter($data), BookingPeriod::rules())),
            'plan' => $plan,
            'requirements' => $data,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $period = BookingPeriod::from($data, future: true);
        $plan = null;

        if (! empty($data['planner'])) {
            $plan = $this->planner->decode($data['planner']);
            $room = Room::query()->find($data['room_id'] ?? null);

            if (! $room || $plan['participants'] !== (int) $data['participants'] || $plan['committee'] !== (int) $data['committee']) {
                throw ValidationException::withMessages(['planner' => 'Update the room plan to match the booking’s attendance.']);
            }

            $result = $this->planner->generate($room, $plan);
            if ($result['error']) {
                throw ValidationException::withMessages(['planner' => $result['error']]);
            }

            $plan['roomLayout'] = $room->layout;
        }

        $items = [];
        foreach ($data['quantities'] ?? [] as $id => $quantity) {
            $items[] = ['equipment_id' => $id, 'quantity' => (int) $quantity];
        }

        $id = $this->reservations->reserve($request->user(), $data, $period, $items, $plan);

        return redirect()->route('bookings.show', $id)->with('status', 'Pengajuan terkirim. Kamu dapat melacak statusnya di Peminjaman Saya.');
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('view', $booking);

        return $this->detail($booking, false);
    }

    public function adminShow(Booking $booking): View
    {
        Gate::authorize('manage', $booking);

        return $this->detail($booking, true);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->reservations->cancel($request->user(), $booking);

        return back()->with('status', 'Pengajuan dibatalkan.');
    }

    public function review(ReviewBookingRequest $request, Booking $booking): RedirectResponse
    {
        $approved = $request->validated('decision') === 'approve';
        $this->reservations->review($request->user(), $booking, $approved, $request->validated('reason'));

        return back()->with('status', $approved ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    public function pickup(Request $request, Booking $booking): RedirectResponse
    {
        $this->inventory->pickup($request->user(), $booking);

        return back()->with('status', 'Equipment pickup recorded.');
    }

    public function returnEquipment(ReturnEquipmentRequest $request, Booking $booking): RedirectResponse
    {
        $warning = $this->inventory->returnItems(
            $request->user(), $booking, array_values($request->validated('items')), $request->validated('note'),
        );

        return back()->with('status', $warning
            ? 'Return recorded. Stock losses affect approved allocations.'
            : 'Equipment return recorded.');
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        $this->reservations->completeRoom($request->user(), $booking);

        return back()->with('status', 'Reservation completed.');
    }

    private function listing(Request $request, Builder $query, bool $admin): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(Booking::STATUSES)]])['status'] ?? null;

        if ($status) {
            $query->where('status', $status);
        }

        return view('bookings.index', [
            'bookings' => $query->with(['room', 'profile'])->orderByDesc('starts_at')->orderBy('id')->paginate(12)->withQueryString(),
            'status' => $status,
            'statuses' => Booking::STATUSES,
            'admin' => $admin,
        ]);
    }

    private function detail(Booking $booking, bool $admin): View
    {
        return view('bookings.show', [
            'booking' => $booking->load(['room', 'profile', 'items.equipment', 'loans.equipment']),
            'admin' => $admin,
        ]);
    }
}
