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

    public function resourcesFor(User $user, array $periods): array
    {
        $intervals = array_values(array_map(fn (BookingPeriod $period) => $period->bindings(), $periods));

        return $this->actor->run($user, function () use ($periods, $intervals): array {
            $rows = DB::select(<<<'SQL'
                select public.resource_availability((item->>0)::timestamptz, (item->>1)::timestamptz) as resources
                from jsonb_array_elements(?::jsonb) with ordinality as requested(item, position)
                order by position
                SQL, [json_encode($intervals, JSON_THROW_ON_ERROR)]);

            return array_combine(array_keys($periods), array_map(
                fn (object $row) => json_decode($row->resources, true, flags: JSON_THROW_ON_ERROR),
                $rows,
            ));
        });
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
