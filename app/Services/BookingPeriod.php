<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class BookingPeriod
{
    public function __construct(public CarbonImmutable $startsAt, public CarbonImmutable $endsAt) {}

    public static function rules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'date' => [$presence, 'date_format:Y-m-d'],
            'start' => [$presence, 'date_format:H:i'],
            'end_date' => [$presence, 'date_format:Y-m-d'],
            'end' => [$presence, 'date_format:H:i'],
        ];
    }

    public static function from(array $data, bool $future = false): self
    {
        $zone = config('campusspace.timezone');
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['start'], $zone);
        $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['end_date'].' '.$data['end'], $zone);

        if ($end <= $start || ($future && $start <= now())) {
            throw ValidationException::withMessages(['date' => $future
                ? 'Pilih waktu mulai yang belum lewat dan waktu selesai setelahnya.'
                : 'Waktu selesai harus setelah waktu mulai.']);
        }

        return new self($start, $end);
    }

    public static function defaults(): array
    {
        $date = now(config('campusspace.timezone'))->addDay()->format('Y-m-d');

        return ['date' => $date, 'start' => '09:00', 'end_date' => $date, 'end' => '11:00'];
    }

    public function bindings(): array
    {
        return [$this->startsAt->toIso8601String(), $this->endsAt->toIso8601String()];
    }
}
