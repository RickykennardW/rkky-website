<?php

namespace Tests\Unit;

use App\Services\BookingPeriod;
use App\Services\SchedulingRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchedulingRulesTest extends TestCase
{
    public static function intervals(): array
    {
        return [
            'exact' => ['13:00', '15:00', true],
            'partial' => ['14:00', '16:00', true],
            'contains' => ['12:00', '16:00', true],
            'contained' => ['13:30', '14:00', true],
            'touches end' => ['15:00', '17:00', false],
            'touches start' => ['12:00', '13:00', false],
            'separate' => ['17:00', '18:00', false],
        ];
    }

    #[DataProvider('intervals')]
    public function test_overlap_uses_half_open_intervals(string $start, string $end, bool $expected): void
    {
        $this->assertSame($expected, SchedulingRules::overlaps($this->period($start, $end), $this->period('13:00', '15:00')));
    }

    public function test_equipment_peak_counts_simultaneous_allocations(): void
    {
        $this->assertSame(5, SchedulingRules::peakAllocation($this->period('13:00', '17:00'), [
            [$this->period('13:00', '15:00'), 3],
            [$this->period('15:00', '17:00'), 4],
            [$this->period('14:00', '16:00'), 1],
            [$this->period('17:00', '18:00'), 10],
        ]));
    }

    private function period(string $start, string $end): BookingPeriod
    {
        return new BookingPeriod(
            CarbonImmutable::parse("2030-03-01 {$start}", 'Asia/Jakarta'),
            CarbonImmutable::parse("2030-03-01 {$end}", 'Asia/Jakarta'),
        );
    }
}
