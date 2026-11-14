<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchedulingRules
{
    public static function overlaps(BookingPeriod $requested, BookingPeriod $existing): bool
    {
        return $requested->startsAt < $existing->endsAt && $requested->endsAt > $existing->startsAt;
    }

    public static function roomIssues(Room $room, int $attendance, array $facilities): array
    {
        $issues = [];
        if (! $room->active) {
            $issues[] = 'Ruangan tidak tersedia untuk dipinjam.';
        }
        if ($room->capacity < $attendance) {
            $issues[] = "Kapasitas {$room->capacity} orang kurang untuk {$attendance} orang.";
        }
        $missing = array_diff($facilities, $room->facilities->pluck('facility')->all());
        if ($missing !== []) {
            $issues[] = 'Fasilitas tidak tersedia · '.implode(', ', array_map(fn ($name) => __('campusspace.facilities.'.$name), $missing)).'.';
        }

        return $issues;
    }

    public static function facilityRules(): array
    {
        return [
            'required_facilities' => ['nullable', 'array', 'max:6'],
            'required_facilities.*' => ['required', 'distinct', Rule::in(Room::FACILITIES)],
        ];
    }

    public static function equipmentSelection(array $quantities): array
    {
        foreach (array_keys($quantities) as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                throw ValidationException::withMessages(['quantities' => 'Pilih peralatan dari katalog.']);
            }
        }

        return array_filter(array_map('intval', $quantities));
    }

    public static function equipmentRules(): array
    {
        return [
            'quantities' => ['nullable', 'array', 'max:100'],
            'quantities.*' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    public static function equipmentIssues(array $requested, array $remaining, array $names): array
    {
        $issues = [];
        foreach ($requested as $id => $quantity) {
            $available = $remaining[$id] ?? 0;
            if ($available < $quantity) {
                $issues[] = ($names[$id] ?? 'Peralatan pilihan')." · {$available} tersedia, {$quantity} diminta.";
            }
        }

        return $issues;
    }

    public static function peakAllocation(BookingPeriod $requested, array $allocations): int
    {
        $events = [];
        foreach ($allocations as [$period, $quantity]) {
            if (! self::overlaps($requested, $period)) {
                continue;
            }
            $start = max($requested->startsAt->getTimestamp(), $period->startsAt->getTimestamp());
            $end = min($requested->endsAt->getTimestamp(), $period->endsAt->getTimestamp());
            $events[$start] = ($events[$start] ?? 0) + $quantity;
            $events[$end] = ($events[$end] ?? 0) - $quantity;
        }
        ksort($events);
        $current = $peak = 0;
        foreach ($events as $delta) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
