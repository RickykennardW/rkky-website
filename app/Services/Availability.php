<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class Availability
{
    public function __construct(private readonly DatabaseActor $actor) {}

    public function resources(User $user, BookingPeriod $period): array
    {
        return $this->actor->run($user, fn (): array => json_decode(
            DB::scalar('select public.resource_availability(?::timestamptz, ?::timestamptz)', $period->bindings()),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function week(User $user, Room $room, CarbonImmutable $start): array
    {
        $period = new BookingPeriod($start, $start->addDays(7));
        $slots = $this->actor->run($user, fn (): array => DB::select(
            'select starts_at, ends_at from public.room_schedule(?::uuid, ?::timestamptz, ?::timestamptz)',
            [$room->id, ...$period->bindings()],
        ));

        $zone = $start->timezoneName;
        $intervals = array_map(fn (object $slot): array => [
            CarbonImmutable::parse($slot->starts_at)->timezone($zone),
            CarbonImmutable::parse($slot->ends_at)->timezone($zone),
        ], $slots);
        $days = [];

        for ($index = 0; $index < 7; $index++) {
            $day = $start->addDays($index);
            $tomorrow = $day->addDay();
            $occupied = [];

            foreach ($intervals as [$begins, $ends]) {
                if ($begins < $tomorrow && $ends > $day) {
                    $occupied[] = ($begins < $day ? '00:00' : $begins->format('H:i')).'–'
                        .($ends >= $tomorrow ? '24:00' : $ends->format('H:i'));
                }
            }

            $days[] = ['date' => $day, 'occupied' => $occupied];
        }

        return $days;
    }
}
