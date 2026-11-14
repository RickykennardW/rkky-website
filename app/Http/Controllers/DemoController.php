<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Room;
use App\Services\BookingPeriod;
use App\Services\DemoWorkspace;
use App\Services\SchedulingRules;
use App\Services\SmartRoomPlanner;
use App\Services\SmartScheduling;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DemoController extends Controller
{
    public function __construct(private readonly DemoWorkspace $demo) {}

    public function login(): View
    {
        return view('auth.demo');
    }

    public function enter(Request $request): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', 'in:student,admin']])['role'];
        $request->session()->regenerate();
        $request->session()->put('campusspace_demo.role', $role);

        return redirect()->route($role === 'admin' ? 'admin.dashboard' : 'dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            return view('dashboard', ['user' => null]);
        }

        $bookings = $this->demo->bookings($request);

        return view('dashboard', [
            'user' => $user, 'rooms' => $this->demo->rooms(),
            'latestBooking' => $bookings->last(fn (Booking $booking) => $booking->id !== 'demo-active'),
        ]);
    }

    public function rooms(Request $request): View
    {
        [$filter, $period] = $this->filters($request, [
            'capacity' => ['nullable', 'integer', 'between:1,1000'],
            'committee' => ['nullable', 'integer', 'between:0,500'],
            ...SchedulingRules::equipmentRules(),
            'facility' => ['nullable', 'array', 'max:6'],
            'facility.*' => ['required', Rule::in(Room::FACILITIES), 'distinct'],
        ]);
        $resources = $period ? $this->demo->availability($request, $period) : null;
        $available = $resources['rooms'] ?? null;
        if ($available !== null && SchedulingRules::equipmentIssues(
            SchedulingRules::equipmentSelection($filter['quantities'] ?? []), $resources['equipment'], [],
        )) {
            $available = [];
        }
        $rooms = $this->demo->rooms()->filter(fn (Room $room) => $room->capacity >= ($filter['capacity'] ?? 1)
            && array_diff($filter['facility'] ?? [], $room->facilities->pluck('facility')->all()) === []
            && ($available === null || ($available[$room->id] ?? false)));

        return view('catalog.rooms', [
            'rooms' => $this->demo->page($rooms), 'filter' => $filter, 'availability' => $available, 'facilities' => Room::FACILITIES,
        ]);
    }

    public function room(Request $request, string $room): View
    {
        $room = $this->demo->rooms()->firstWhere('id', $room);
        abort_if($room === null, 404);
        $filter = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'], ...BookingPeriod::rules(false), ...SmartScheduling::inputRules(false),
        ]);
        $zone = config('campusspace.timezone');
        $week = isset($filter['week']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filter['week'], $zone) : CarbonImmutable::today($zone);
        $approved = $this->demo->bookings($request)->where('status', 'approved')->where('room_id', $room->id);
        $calendar = collect(range(0, 6))->map(function (int $index) use ($week, $approved): array {
            $day = $week->addDays($index);
            $end = $day->addDay();
            $occupied = $approved->filter(fn (Booking $booking) => $booking->starts_at < $end && $booking->ends_at > $day)
                ->map(fn (Booking $booking) => ($booking->starts_at < $day ? '00:00' : $booking->starts_at->timezone($day->timezone)->format('H:i')).'–'
                    .($booking->ends_at >= $end ? '24:00' : $booking->ends_at->timezone($day->timezone)->format('H:i')));

            return ['date' => $day, 'occupied' => $occupied->values()->all()];
        });

        return view('catalog.room', compact('room', 'week', 'calendar') + ['filter' => array_filter($filter)]);
    }

    public function equipment(Request $request): View
    {
        [$filter, $period] = $this->filters($request, [
            'category' => ['nullable', Rule::in(Equipment::CATEGORIES)], 'available' => ['nullable', 'boolean'],
        ]);
        $available = $period ? $this->demo->availability($request, $period)['equipment'] : null;
        $equipment = $this->demo->equipment()->filter(fn (Equipment $item) => (! isset($filter['category']) || $item->category === $filter['category'])
            && (! $request->boolean('available') || ($available[$item->id] ?? $item->usableQuantity()) > 0));

        return view('catalog.equipment', [
            'equipment' => $this->demo->page($equipment), 'filter' => $filter, 'availability' => $available, 'categories' => Equipment::CATEGORIES,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        [, $period] = $this->filters($request, [], true);

        return response()->json($this->demo->availability($request, $period))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request, SmartRoomPlanner $planner): View
    {
        $data = $request->validate([
            'room' => ['nullable', 'uuid'], 'equipment' => ['nullable', 'uuid'], 'plan' => ['nullable', 'string', 'max:16000'],
            ...BookingPeriod::rules(false), ...SmartScheduling::inputRules(false),
        ]);
        $rooms = $this->demo->rooms();
        $room = $rooms->firstWhere('id', $data['room'] ?? null);

        return view('bookings.create', [
            'rooms' => $rooms, 'equipment' => $this->demo->equipment(), 'selectedRoom' => $room,
            'selectedEquipment' => $data['equipment'] ?? null,
            'period' => array_replace(BookingPeriod::defaults(), array_intersect_key(array_filter($data), BookingPeriod::rules())),
            'plan' => $room && isset($data['plan']) ? $planner->decode($data['plan']) : null,
            'requirements' => $data,
        ]);
    }

    public function store(StoreBookingRequest $request, SmartRoomPlanner $planner): RedirectResponse
    {
        $data = $request->validated();
        $room = $this->demo->rooms()->firstWhere('id', $data['room_id'] ?? null);
        if (isset($data['room_id']) && (! $room || $room->capacity < $data['participants'] + $data['committee'])) {
            throw ValidationException::withMessages(['room_id' => 'Pilih ruangan yang cukup untuk peserta dan panitia.']);
        }

        $plan = empty($data['planner']) ? null : $planner->decode($data['planner']);
        if ($plan && (! $room || $plan['participants'] !== (int) $data['participants'] || $plan['committee'] !== (int) $data['committee']
            || $planner->generate($room, $plan)['error'])) {
            throw ValidationException::withMessages(['planner' => 'Update the room plan to fit the booking.']);
        }
        $id = $this->demo->save($request, $data, BookingPeriod::from($data, future: true), $plan);

        return redirect()->route('bookings.show', $id)->with('status', 'Pengajuan terkirim. Kamu dapat melacak statusnya di Peminjaman Saya.');
    }

    public function bookings(Request $request): View
    {
        $admin = $request->routeIs('admin.*');
        $status = $request->validate(['status' => ['nullable', Rule::in(Booking::STATUSES)]])['status'] ?? null;
        $bookings = $this->demo->bookings($request)->sortByDesc('starts_at');

        return view('bookings.index', [
            'bookings' => $this->demo->page($status ? $bookings->where('status', $status) : $bookings),
            'status' => $status, 'statuses' => Booking::STATUSES, 'admin' => $admin,
        ]);
    }

    public function booking(Request $request, string $booking): View
    {
        return view('bookings.show', ['booking' => $this->findBooking($request, $booking), 'admin' => $request->routeIs('admin.*')]);
    }

    public function cancel(Request $request, string $booking): RedirectResponse
    {
        Gate::authorize('cancel', $this->findBooking($request, $booking));
        $this->demo->decide($request, $booking, 'cancelled');

        return back()->with('status', 'Pengajuan dibatalkan.');
    }

    public function review(Request $request, string $booking): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'between:3,1000']]);
        $status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
        $this->demo->decide($request, $booking, $status, $data['reason'] ?? null);

        return back()->with('status', $status === 'approved' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    public function admin(Request $request): View
    {
        $bookings = $this->demo->bookings($request);
        $pending = $bookings->where('status', 'pending');
        $today = now(config('campusspace.timezone'))->startOfDay()->toImmutable();

        return view('admin.dashboard', [
            'pendingCount' => $pending->count(), 'activeLoans' => 0, 'overdue' => 0, 'losses' => 0,
            'pending' => $pending->sortBy('starts_at')->take(5),
            'todayBookings' => $bookings->whereIn('status', ['approved', 'completed'])
                ->filter(fn (Booking $booking) => $booking->starts_at < $today->addDay() && $booking->ends_at > $today)
                ->sortBy('starts_at')->take(8),
        ]);
    }

    private function findBooking(Request $request, string $id): Booking
    {
        $booking = $this->demo->bookings($request)->firstWhere('id', $id);
        abort_if($booking === null, 404);

        return $booking;
    }

    private function filters(Request $request, array $rules, bool $required = false): array
    {
        if ($request->filled('endDate') && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('endDate')]);
        }
        if ($request->filled(['date', 'start', 'end']) && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('date')]);
        }
        $hasPeriod = $required || $request->anyFilled(['date', 'start', 'end_date', 'end']);
        $filter = $request->validate([...$rules, ...BookingPeriod::rules($hasPeriod)]);

        return [$filter, $hasPeriod ? BookingPeriod::from($filter) : null];
    }
}
