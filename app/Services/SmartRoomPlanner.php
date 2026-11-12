<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class SmartRoomPlanner
{
    public const EVENTS = ['Seminar', 'Meeting', 'Workshop', 'Presentation', 'General Event'];

    public const ARRANGEMENTS = ['Theater', 'Classroom', 'U-Shape', 'Group / Cluster'];

    public const QUANTITIES = ['chairs', 'tables', 'microphones', 'projectors', 'cables'];

    public const DEFAULTS = [
        'eventType' => 'Seminar', 'participants' => 24, 'committee' => 4,
        'presentation' => true, 'registrationDesk' => false, 'discussion' => false,
    ];

    public static function rules(): array
    {
        return [
            'eventType' => ['required', Rule::in(self::EVENTS)],
            'participants' => ['required', 'integer', 'between:1,500'],
            'committee' => ['required', 'integer', 'between:0,500'],
            'presentation' => ['required', 'boolean'],
            'registrationDesk' => ['required', 'boolean'],
            'discussion' => ['required', 'boolean'],
            'arrangement' => ['nullable', Rule::in(self::ARRANGEMENTS)],
            ...array_fill_keys(self::QUANTITIES, ['nullable', 'integer', 'between:0,500']),
        ];
    }

    public function estimate(array $input): array
    {
        $participants = (int) $input['participants'];
        $committee = (int) $input['committee'];
        $presentation = (bool) $input['presentation'];
        $registration = (bool) $input['registrationDesk'];
        $discussion = (bool) $input['discussion'];
        $arrangement = $input['arrangement'] ?? match (true) {
            $discussion || $input['eventType'] === 'Workshop' => 'Group / Cluster',
            $input['eventType'] === 'Meeting' => 'U-Shape',
            default => 'Theater',
        };

        $chairs = (int) ($input['chairs'] ?? $participants + ceil($committee / 2));
        $tableSeats = $arrangement === 'Group / Cluster' ? 4 : 2;

        return [
            'eventType' => $input['eventType'], 'participants' => $participants, 'committee' => $committee,
            'presentation' => $presentation, 'registrationDesk' => $registration, 'discussion' => $discussion,
            'arrangement' => $arrangement,
            'chairs' => $chairs,
            'tables' => (int) ($input['tables'] ?? (($arrangement === 'Theater' ? 0 : ceil($chairs / $tableSeats)) + (int) $registration)),
            'microphones' => (int) ($input['microphones'] ?? ($presentation ? ($participants >= 50 ? 2 : 1) : 0)),
            'projectors' => (int) ($input['projectors'] ?? (int) $presentation),
            'cables' => (int) ($input['cables'] ?? ((int) $presentation + (int) $registration)),
        ];
    }

    public function decode(string $serialized): array
    {
        try {
            $plan = json_decode($serialized, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $plan = null;
        }

        if (! is_array($plan)) {
            throw ValidationException::withMessages(['planner' => 'Open the room planner and save a valid plan.']);
        }

        return $this->estimate(Validator::make($plan, self::rules())->validate());
    }

    public function generate(Room $room, array $plan): array
    {
        if (! RoomGeometry::isUsable($room->layout)) {
            return $this->result($plan, [], null, 'An administrator needs to configure the room dimensions and door.');
        }

        $layout = RoomGeometry::normalize($room->layout);
        $width = $layout['width'];
        $depth = $layout['depth'];
        $door = $layout['door'];
        $obstacles = [
            ...array_map(fn (array $zone): array => [...$zone, 'kind' => 'blocked'], $layout['blocked']),
            ...array_map(fn (array $zone): array => [...$zone, 'kind' => 'fixed'], $layout['fixed']),
        ];
        $entry = match ($door['wall']) {
            'north' => $this->rectangle('clearance', $door['offset'], 0, $door['width'], $door['clearance']),
            'south' => $this->rectangle('clearance', $door['offset'], $depth - $door['clearance'], $door['width'], $door['clearance']),
            'west' => $this->rectangle('clearance', 0, $door['offset'], $door['clearance'], $door['width']),
            'east' => $this->rectangle('clearance', $width - $door['clearance'], $door['offset'], $door['clearance'], $door['width']),
        };
        $aisle = $this->rectangle('clearance', $width / 2 - 0.45, 0.25, 0.9, $depth - 0.5);
        $corridorCenter = $entry['x'] + $entry['width'] / 2 - 0.45;
        $corridorLeft = min($corridorCenter, $aisle['x']);
        $corridor = $this->rectangle(
            'clearance',
            max(0, $corridorLeft),
            max(0, min($depth - 0.9, $entry['y'] + $entry['depth'] / 2 - 0.45)),
            min(abs($corridorCenter - $aisle['x']) + 0.9 + min($corridorLeft, 0), $width - max($corridorLeft, 0)),
            0.9,
        );
        $clearances = [$entry, $aisle, $corridor];
        $base = [...$obstacles, ...$clearances];
        $invalid = fn (string $message): array => $this->result($plan, $base, $layout, $message);

        if ($room->capacity < $plan['participants'] + $plan['committee']) {
            return $invalid('Total attendance exceeds this room’s capacity.');
        }
        if ($plan['chairs'] < $plan['participants']) {
            return $invalid('Provide at least one chair for each participant.');
        }
        if ($plan['chairs'] > $room->chairs || $plan['tables'] > $room->tables_count) {
            return $invalid('This plan exceeds the room’s furniture inventory.');
        }
        foreach ($clearances as $area) {
            foreach ($obstacles as $obstacle) {
                if ($this->overlaps($area, $obstacle)) {
                    return $invalid('The configured door clearance or aisle crosses a blocked zone.');
                }
            }
        }
        if ($plan['registrationDesk'] && $plan['tables'] < 1) {
            return $invalid('Add one table for the registration desk.');
        }

        $items = $base;
        $fits = function (array $objects) use (&$items, $width, $depth): bool {
            foreach ($objects as $object) {
                if ($object['x'] < 0.2 || $object['y'] < 0.2
                    || $width - 0.2 < $object['x'] + $object['width']
                    || $depth - 0.2 < $object['y'] + $object['depth']) {
                    return false;
                }
                foreach ($items as $existing) {
                    if ($this->overlaps($object, $existing)) {
                        return false;
                    }
                }
            }

            return true;
        };
        $place = function (string $kind, float $itemWidth, float $itemDepth, bool $nearBack = false) use (&$items, $fits, $width, $depth): bool {
            $rows = $this->positions(0.35, $depth - $itemDepth - 0.2, 0.45);
            $columns = $this->positions(0.35, $width - $itemWidth - 0.2, 0.45);

            foreach ($nearBack ? array_reverse($rows) : $rows as $y) {
                foreach ($columns as $x) {
                    $item = $this->rectangle($kind, $x, $y, $itemWidth, $itemDepth);
                    if ($fits([$item])) {
                        $items[] = $item;

                        return true;
                    }
                }
            }

            return false;
        };

        if ($plan['presentation'] && ! $place('stage', min(3, $width / 2 - 0.7), 0.9)) {
            return $invalid('There is no clear space for a presentation area.');
        }
        if ($plan['registrationDesk'] && ! $place('registration', 1.4, 0.65, $door['wall'] !== 'north')) {
            return $invalid('The registration desk cannot fit without blocking access.');
        }

        $seats = $plan['chairs'];
        $tables = $plan['tables'] - (int) $plan['registrationDesk'];
        $arrangement = $plan['arrangement'];
        $group = $arrangement === 'Group / Cluster';
        $startY = $plan['presentation'] ? 1.6 : 0.45;
        $placeBundle = function (float $x, float $y, int $size, bool $vertical = false, bool $mirrored = false) use (&$items, &$seats, &$tables, $fits, $group): void {
            if ($seats < 1 || $tables < 1) {
                return;
            }

            $bundle = [$this->rectangle('table', $x + ($vertical && ! $mirrored ? 0.56 : 0), $y + ($group ? 0.54 : 0),
                $vertical ? 0.62 : 1.35, $vertical ? 1.35 : 0.64)];
            $chairCount = min($seats, $size);
            for ($index = 0; $index < $chairCount; $index++) {
                $position = $index % 2 * 0.67 + 0.08;
                $chairX = $vertical ? $x + ($mirrored ? 0.74 : 0) : $x + $position;
                $chairY = $vertical ? $y + $position : $y + ($group ? ($index < 2 ? 0 : 1.31) : 0.78);
                $bundle[] = $this->rectangle('chair', $chairX, $chairY, 0.45, 0.45);
            }
            if ($fits($bundle)) {
                array_push($items, ...$bundle);
                $seats -= $chairCount;
                $tables--;
            }
        };

        if ($arrangement === 'U-Shape') {
            foreach ($this->positions(0.5, $width - 1.6, 1.65) as $x) {
                $placeBundle($x, $startY, 2);
            }
            foreach ($this->positions($startY + 1.55, $depth - 1.55, 1.65) as $y) {
                $placeBundle(0.45, $y, 2, true);
                $placeBundle($width - 1.65, $y, 2, true, true);
            }
        } elseif ($arrangement === 'Theater') {
            $columns = $this->positions(0.45, $width - 0.7, 0.65);
            foreach ($this->positions($startY, $depth - 0.7, 0.85) as $y) {
                foreach ($columns as $x) {
                    $chair = $this->rectangle('chair', $x, $y, 0.45, 0.45);
                    if ($seats > 0 && $fits([$chair])) {
                        $items[] = $chair;
                        $seats--;
                    }
                }
            }
        } else {
            $columns = $this->positions(0.45, $width - 1.5, $group ? 1.95 : 1.65);
            foreach ($this->positions($startY, $depth - 0.85, $group ? 2.1 : 1.55) as $y) {
                foreach ($columns as $x) {
                    $placeBundle($x, $y, $group ? 4 : 2);
                }
            }
        }

        if ($seats > 0) {
            return $invalid("This arrangement could not place {$seats} requested seats while keeping access clear.");
        }

        while ($tables > 0) {
            if (! $place('table', 1.35, 0.65, true)) {
                return $invalid('There is insufficient clear space for all requested tables.');
            }
            $tables--;
        }

        return $this->result($plan, $items, $layout);
    }

    public function overlaps(array $first, array $second): bool
    {
        $tolerance = 0.00001;

        return $first['x'] < $second['x'] + $second['width'] - $tolerance
            && $first['x'] + $first['width'] > $second['x'] + $tolerance
            && $first['y'] < $second['y'] + $second['depth'] - $tolerance
            && $first['y'] + $first['depth'] > $second['y'] + $tolerance;
    }

    private function positions(float $start, float $end, float $step): array
    {
        $positions = [];
        for ($value = $start; $value <= $end; $value += $step) {
            $positions[] = round($value, 2);
        }

        return $positions;
    }

    private function rectangle(string $kind, float $x, float $y, float $width, float $depth): array
    {
        return compact('kind', 'x', 'y', 'width', 'depth');
    }

    private function result(array $plan, array $items, ?array $layout, ?string $error = null): array
    {
        return compact('plan', 'items', 'layout', 'error');
    }
}
