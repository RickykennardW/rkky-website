<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RoomMonitoring
{
    public function snapshot(Collection $rooms, Collection $bookings, CarbonImmutable $now, bool $admin): array
    {
        $approved = $bookings->where('status', 'approved')->filter(fn (Booking $booking) => $booking->ends_at > $now);
        $byRoom = $approved->sortBy('starts_at')->groupBy('room_id');
        $cards = $rooms->map(function (Room $room) use ($byRoom, $now, $admin): array {
            $bookings = $byRoom->get($room->id, collect());
            $current = $bookings->first(fn (Booking $booking) => $booking->starts_at <= $now);
            $next = $bookings->first(fn (Booking $booking) => $booking->starts_at > $now);

            return [
                'id' => $room->id, 'name' => $room->name, 'location' => $room->location, 'capacity' => $room->capacity,
                'status' => match (true) {
                    ! $room->active => 'unavailable',
                    $current !== null => 'in use',
                    $next !== null => 'upcoming',
                    default => 'available',
                },
                'current' => $this->period($current, $admin), 'next' => $this->period($next, $admin),
            ];
        })->values();

        return [
            'server_time' => $now->getTimestampMs(), 'timezone' => config('campusspace.timezone'),
            'summary' => [
                'total' => $cards->count(), 'available' => $cards->whereIn('status', ['available', 'upcoming'])->count(),
                'in_use' => $cards->where('status', 'in use')->count(),
                'upcoming' => $approved->filter(fn (Booking $booking) => $booking->starts_at > $now)->count(),
                'unavailable' => $cards->where('status', 'unavailable')->count(),
            ],
            'rooms' => $cards->all(),
        ];
    }

    private function period(?Booking $booking, bool $admin): ?array
    {
        return $booking ? [
            'start' => $booking->starts_at->getTimestampMs(), 'end' => $booking->ends_at->getTimestampMs(),
            ...($admin ? ['title' => $booking->title, 'url' => route('admin.bookings.show', $booking)] : []),
        ] : null;
    }
}
