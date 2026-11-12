<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Profile;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DemoWorkspace
{
    public const STUDENT = 'demo-student';

    public static function user(string $role): User
    {
        $user = new User;
        $user->setRawAttributes(['id' => 'demo-'.$role]);
        $user->setRelation('profile', (new Profile)->forceFill([
            'full_name' => $role === 'admin' ? 'Demo Administrator' : 'Demo Student',
            'email' => $role.'@campusspace.example',
            'role' => $role,
        ]));

        return $user;
    }

    public function rooms(): Collection
    {
        return $this->allRooms()->where('active', true)->values();
    }

    public function allRooms(): Collection
    {
        return collect([
            ['Seminar Room A', 'Innovation Center · Level 2', 'A flexible room for talks, panels, and committee events.', 80, 20, 'seminar', 14, 12, ['Projector', 'Air conditioning', 'Audio system', 'Wi-Fi']],
            ['Classroom B', 'Learning Commons · Level 1', 'A classroom with movable desks for workshops and group discussions.', 36, 18, 'classroom', 12, 10, ['Whiteboard', 'Air conditioning', 'Wi-Fi']],
            ['Multipurpose Hall C', 'Student Center · Ground floor', 'A hall for campus gatherings with an acoustic stage and accessible entry.', 150, 35, 'hall', 20, 16, ['Projector', 'Audio system', 'Accessible entry']],
            ['Studio D', 'Arts Building · Level 1', 'A studio temporarily unavailable for maintenance.', 24, 12, 'classroom', 10, 9, ['Whiteboard']],
        ])->map(function (array $fixture, int $index): Room {
            [$name, $location, $description, $capacity, $tables, $photo, $width, $depth, $facilities] = $fixture;
            $room = (new Room)->forceFill([
                'id' => sprintf('a1000000-0000-4000-8000-%012d', $index + 1),
                'name' => $name, 'location' => $location, 'description' => $description,
                'capacity' => $capacity, 'chairs' => $capacity, 'tables_count' => $tables,
                'active' => $index !== 3, 'photo' => $photo,
                'layout' => ['width' => $width, 'depth' => $depth,
                    'door' => ['wall' => 'south', 'offset' => $width / 2 - 0.6, 'width' => 1.2, 'clearance' => 1.2],
                    'blocked' => [], 'fixed' => []],
            ]);

            return $room->setRelation('facilities', collect($facilities)->map(fn ($name) => (object) ['facility' => $name]));
        });
    }

    public function equipment(): Collection
    {
        return collect([
            ['Extension Cable', 'Power', 'A grounded extension reel for event power.', 16],
            ['Wireless Microphone', 'Audio', 'A handheld microphone with receiver.', 8],
            ['Portable Projector', 'Presentation', 'An HDMI projector with a carry case.', 5],
            ['Portable Speaker', 'Audio', 'A rechargeable speaker for workshops.', 6],
            ['Folding Table', 'Furniture', 'A lightweight table for check-in or group activities.', 24],
            ['Stacking Chair', 'Furniture', 'Stackable seating for campus events.', 80],
        ])->map(function (array $fixture, int $index): Equipment {
            [$name, $category, $description, $quantity] = $fixture;

            return (new Equipment)->forceFill([
                'id' => sprintf('a2000000-0000-4000-8000-%012d', $index + 1),
                'name' => $name, 'category' => $category, 'description' => $description,
                'total_quantity' => $quantity, 'unusable_quantity' => 0, 'status' => 'available',
            ]);
        });
    }

    public function bookings(Request $request): Collection
    {
        $rooms = $this->rooms()->keyBy('id');
        $equipment = $this->equipment()->keyBy('id');
        $profile = self::user('student')->profile;

        return collect($this->rows($request))
            ->map(function (array $data) use ($rooms, $equipment, $profile): Booking {
                $booking = (new Booking)->setDateFormat('Y-m-d H:i:s');
                $booking->setRawAttributes(collect($data)->except(['quantities', 'planner', 'required_facilities'])->all());
                $booking->forceFill(['planner' => $data['planner'], 'required_facilities' => $data['required_facilities'] ?? []]);

                return $booking
                    ->setRelation('room', $rooms->get($data['room_id']))
                    ->setRelation('profile', $profile)
                    ->setRelation('loans', collect())
                    ->setRelation('items', collect($data['quantities'])->map(fn ($quantity, $id) => (object) [
                        'equipment_id' => $id, 'quantity' => $quantity, 'equipment' => $equipment->get($id),
                    ]));
            });
    }

    private function rows(Request $request): array
    {
        return $request->session()->remember('campusspace_demo.bookings', fn () => $this->examples());
    }

    private function examples(): array
    {
        $room = $this->rooms()->first();
        $day = CarbonImmutable::tomorrow(config('campusspace.timezone'));

        $examples = collect(['approved' => 'Robotics orientation', 'pending' => 'Student design workshop', 'rejected' => 'Photography briefing'])
            ->map(function (string $title, string $status) use ($room, $day): array {
                $start = $day->setTime(13, 0)->addDays(array_search($status, ['approved', 'pending', 'rejected'], true));

                return [
                    'id' => 'demo-'.$status, 'title' => $title, 'description' => 'Sample request for the CampusSpace presentation.',
                    'user_id' => self::STUDENT, 'room_id' => $room->id, 'participants' => 24, 'committee' => 3,
                    'starts_at' => $start->toIso8601String(), 'ends_at' => $start->addHours(2)->toIso8601String(),
                    'status' => $status, 'quantities' => $status === 'approved' ? [$this->equipment()->firstWhere('name', 'Portable Projector')->id => 1] : [], 'planner' => null,
                    'review_note' => $status === 'rejected' ? 'The organizers requested a different date.' : null,
                ];
            })->values()->all();

        return [...$examples, $this->activeSample($this->rooms()->get(1), now()->toImmutable())];
    }

    private function activeSample(Room $room, CarbonImmutable $now): array
    {
        return [
            'id' => 'demo-active', 'title' => 'Campus orientation rehearsal', 'description' => 'Simulated reservation in progress.',
            'user_id' => self::STUDENT, 'room_id' => $room->id, 'participants' => 18, 'committee' => 2,
            'starts_at' => $now->subMinutes(10)->toIso8601String(), 'ends_at' => $now->addMinutes(10)->toIso8601String(),
            'status' => 'approved', 'quantities' => [], 'planner' => null, 'review_note' => null,
        ];
    }

    public function loadActiveSample(Request $request): void
    {
        $now = now()->toImmutable();
        $period = new BookingPeriod($now->subMinutes(10), $now->addMinutes(10));
        $approved = $this->bookings($request)->where('status', 'approved')->where('id', '!=', 'demo-active');
        $room = $this->rooms()->first(fn (Room $room) => ! $approved->contains(fn (Booking $booking) => $booking->room_id === $room->id && SchedulingRules::overlaps($period, new BookingPeriod($booking->starts_at, $booking->ends_at))));
        if (! $room) {
            throw ValidationException::withMessages(['sample' => 'There is no free room for the sample reservation.']);
        }
        $rows = collect($this->rows($request))->where('id', '!=', 'demo-active')->values()->all();
        $request->session()->put('campusspace_demo.bookings', [...$rows, $this->activeSample($room, $now)]);
    }

    public function availability(Request $request, BookingPeriod $period): array
    {
        $overlapping = $this->bookings($request)->filter(fn (Booking $booking) => $booking->status === 'approved'
            && SchedulingRules::overlaps($period, new BookingPeriod($booking->starts_at, $booking->ends_at)));
        $occupied = $overlapping->pluck('room_id');

        return [
            'rooms' => $this->rooms()->mapWithKeys(fn (Room $room) => [$room->id => ! $occupied->contains($room->id)])->all(),
            'equipment' => $this->equipment()->mapWithKeys(fn (Equipment $item) => [
                $item->id => max(0, $item->usableQuantity() - SchedulingRules::peakAllocation($period, $overlapping
                    ->map(fn (Booking $booking) => [new BookingPeriod($booking->starts_at, $booking->ends_at), $booking->items->get($item->id)?->quantity ?? 0])->all())),
            ])->all(),
        ];
    }

    public function save(Request $request, array $data, BookingPeriod $period, ?array $plan): string
    {
        $rows = $this->rows($request);
        if (count($rows) >= 40) {
            throw ValidationException::withMessages(['title' => 'This demo session has reached its booking limit.']);
        }
        $row = [
            ...collect($data)->only(['title', 'description', 'participants', 'committee', 'quantities', 'required_facilities'])->all(),
            'id' => (string) Str::uuid(), 'user_id' => self::STUDENT, 'room_id' => $data['room_id'] ?? null,
            'starts_at' => $period->startsAt->toIso8601String(), 'ends_at' => $period->endsAt->toIso8601String(),
            'status' => 'pending', 'planner' => $plan, 'review_note' => null,
        ];
        $row['quantities'] ??= [];
        $this->assertAvailable($request, $row);
        $request->session()->put('campusspace_demo.bookings', [...$rows, $row]);

        return $row['id'];
    }

    public function decide(Request $request, string $id, string $status, ?string $reason = null): void
    {
        $rows = collect($this->rows($request));
        $row = $rows->firstWhere('id', $id);
        abort_if($row === null, 404);
        if ($row['status'] !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
        }
        if ($status === 'approved') {
            if (CarbonImmutable::parse($row['starts_at']) <= now()) {
                throw ValidationException::withMessages(['decision' => 'The requested start time has passed.']);
            }
            $this->assertAvailable($request, $row);
        }
        $request->session()->put('campusspace_demo.bookings', $rows->map(fn (array $entry) => $entry['id'] === $id
            ? [...$entry, 'status' => $status, 'review_note' => $reason]
            : $entry)->all());
    }

    private function assertAvailable(Request $request, array $row): void
    {
        $available = $this->availability($request, new BookingPeriod(CarbonImmutable::parse($row['starts_at']), CarbonImmutable::parse($row['ends_at'])));
        if ($row['room_id']) {
            $room = $this->rooms()->firstWhere('id', $row['room_id']);
            $issues = $room ? SchedulingRules::roomIssues($room, $row['participants'] + $row['committee'], $row['required_facilities'] ?? []) : ['Room unavailable.'];
            if ($issues !== []) {
                throw ValidationException::withMessages(['room_id' => $issues]);
            }
            if (! ($available['rooms'][$row['room_id']] ?? false)) {
                throw ValidationException::withMessages(['room_id' => 'This room is already reserved for that period.']);
            }
        }
        $issues = SchedulingRules::equipmentIssues($row['quantities'], $available['equipment'], $this->equipment()->pluck('name', 'id')->all());
        if ($issues !== []) {
            throw ValidationException::withMessages(['quantities' => $issues]);
        }
    }

    public function page(Collection $items): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator($items->forPage($page, 12)->values(), $items->count(), 12, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();
    }
}
