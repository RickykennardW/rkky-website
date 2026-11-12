<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SchedulingRequestText
{
    public function interpret(string $text, Collection $rooms, Collection $equipment): array
    {
        $text = mb_strtolower($text);
        $today = CarbonImmutable::today(config('campusspace.timezone'));
        $date = match (true) {
            preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text, $calendar) === 1 => $calendar[0],
            preg_match('/\b(tomorrow|besok)\b/', $text) === 1 => $today->addDay()->toDateString(),
            preg_match('/\b(today|hari ini)\b/', $text) === 1 => $today->toDateString(),
            default => null,
        };
        preg_match_all('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', $text, $times, PREG_SET_ORDER);
        if (! $date || count($times) !== 2 || ! preg_match('/\b(\d{1,3})\s+(people|participants?|orang|peserta)\b/', $text, $attendees)) {
            throw ValidationException::withMessages(['request_text' => 'Include a date, two 24-hour times, and a participant count, for example “40 people tomorrow from 13:00 to 15:00”.']);
        }
        preg_match('/\b(\d{1,3})\s+(committee(?: members?)?|panitia)\b/', $text, $committee);
        $room = $rooms->first(fn ($room) => str_contains($text, mb_strtolower($room->name)));
        $arrangement = collect(SmartRoomPlanner::ARRANGEMENTS)->first(fn ($layout) => str_contains($text, mb_strtolower($layout)));
        $facilities = [];
        $quantities = [];
        foreach (['Portable Projector' => 'projectors?|proyektor', 'Wireless Microphone' => 'microphones?|mikrofon'] as $name => $terms) {
            $item = $equipment->firstWhere('name', $name);
            if (! $item || ! preg_match('/\b(?:(\d{1,3})\s+)?(?:'.$terms.')\b/', $text, $match)) {
                continue;
            }
            if ($name === 'Portable Projector' && preg_match('/\b(built-in projector|proyektor bawaan)\b/', $text)) {
                $facilities[] = 'Projector';

                continue;
            }
            $quantities[$item->id] = (int) (($match[1] ?? '') ?: 1);
        }

        return [
            'date' => $date, 'end_date' => $date,
            'start' => sprintf('%02d:%02d', $times[0][1], $times[0][2]),
            'end' => sprintf('%02d:%02d', $times[1][1], $times[1][2]),
            'participants' => (int) $attendees[1], 'committee' => (int) ($committee[1] ?? 0),
            'room' => $room?->id, 'arrangement' => $arrangement,
            'required_facilities' => $facilities, 'quantities' => $quantities,
        ];
    }
}
