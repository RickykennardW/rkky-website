<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SmartScheduling
{
    public function __construct(private readonly SmartRoomPlanner $planner) {}

    public static function inputRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'participants' => [$presence, 'integer', 'between:1,500'],
            'committee' => [$presence, 'integer', 'between:0,500'],
            'arrangement' => ['nullable', Rule::in(SmartRoomPlanner::ARRANGEMENTS)],
            'quantities' => ['nullable', 'array', 'max:100'],
            'quantities.*' => ['required', 'integer', 'between:0,10000'],
            ...SchedulingRules::facilityRules(),
        ];
    }

    public function analyze(Collection $rooms, Collection $equipment, BookingPeriod $requested, array $input, callable $availability): array
    {
        $attendance = (int) $input['participants'] + (int) $input['committee'];
        $facilities = $input['required_facilities'] ?? [];
        $quantities = SchedulingRules::equipmentSelection($input['quantities'] ?? []);
        $names = $equipment->pluck('name', 'id')->all();
        $preferred = $input['room'] ?? null;
        $plan = empty($input['arrangement']) ? null : $this->planner->estimate([
            ...SmartRoomPlanner::DEFAULTS,
            'participants' => $input['participants'], 'committee' => $input['committee'],
            'arrangement' => $input['arrangement'], 'presentation' => in_array('Projector', $facilities, true),
        ]);

        $roomIssues = $rooms->mapWithKeys(function (Room $room) use ($attendance, $facilities, $plan): array {
            $issues = SchedulingRules::roomIssues($room, $attendance, $facilities);
            if ($issues === [] && $plan && ($error = $this->planner->generate($room, $plan)['error'])) {
                $issues[] = $error;
            }

            return [$room->id => $issues];
        });
        $periods = [0 => $requested];
        if ($roomIssues->containsStrict([])) {
            foreach ([30, 60, 90, 120, 180, 240] as $minutes) {
                foreach ([-$minutes, $minutes] as $offset) {
                    $starts = $requested->startsAt->addMinutes($offset);
                    if ($starts > now()) {
                        $periods[$offset] = new BookingPeriod($starts, $requested->endsAt->addMinutes($offset));
                    }
                }
            }
        }
        $states = $availability($periods);
        $assessments = [];
        $candidates = [];
        foreach ($periods as $offset => $period) {
            $state = $states[$offset];
            $equipmentIssues = SchedulingRules::equipmentIssues($quantities, $state['equipment'], $names);
            foreach ($rooms as $room) {
                $issues = $roomIssues[$room->id];
                if ($room->active && ! ($state['rooms'][$room->id] ?? false)) {
                    $issues[] = 'An approved reservation overlaps this time.';
                }
                $issues = [...$issues, ...$equipmentIssues];
                if ($offset === 0) {
                    $assessments[] = ['name' => $room->name, 'issues' => $issues];
                }
                if ($issues === []) {
                    $candidates[] = [
                        'room' => $room, 'period' => $period, 'offset' => $offset,
                        'fit' => $room->capacity - $attendance,
                        'reasons' => array_values(array_filter([
                            "Fits {$attendance} people in {$room->capacity} places.",
                            $facilities ? 'Has '.implode(', ', $facilities).'.' : null,
                            $quantities ? 'Requested equipment is available.' : null,
                            $plan ? 'The selected seating layout fits.' : null,
                        ])),
                        'booking' => array_filter([
                            'room' => $room->id,
                            'date' => $period->startsAt->format('Y-m-d'), 'start' => $period->startsAt->format('H:i'),
                            'end_date' => $period->endsAt->format('Y-m-d'), 'end' => $period->endsAt->format('H:i'),
                            'participants' => $input['participants'], 'committee' => $input['committee'],
                            'quantities' => $quantities, 'required_facilities' => $facilities,
                            'plan' => $plan ? json_encode($plan, JSON_THROW_ON_ERROR) : null,
                        ], fn ($value) => $value !== null),
                    ];
                }
            }
        }

        $ranked = collect($candidates)->sortBy(fn (array $candidate) => [
            abs($candidate['offset']), $preferred && $candidate['room']->id !== $preferred ? 1 : 0,
            $candidate['fit'], $candidate['offset'] < 0 ? 1 : 0, $candidate['room']->name,
        ])->values();
        $sameTime = $ranked->where('offset', 0);
        $otherTimes = $ranked->where('offset', '!=', 0);

        return [
            'assessments' => $assessments,
            'sameTime' => $sameTime->take(4),
            'preferredTimes' => $preferred ? $otherTimes->filter(fn ($entry) => $entry['room']->id === $preferred)->take(3) : collect(),
            'otherTimes' => $otherTimes->filter(fn ($entry) => $entry['room']->id !== $preferred)->take(3),
        ];
    }
}
