<?php

namespace Tests\Unit;

use App\Services\SmartRoomPlanner;
use PHPUnit\Framework\TestCase;

class PlannerEstimateTest extends TestCase
{
    public function test_workshop_estimate_includes_registration_and_half_the_committee(): void
    {
        $plan = (new SmartRoomPlanner)->estimate([
            ...SmartRoomPlanner::DEFAULTS,
            'eventType' => 'Workshop', 'participants' => 23, 'committee' => 5, 'registrationDesk' => true,
        ]);

        $this->assertSame('Group / Cluster', $plan['arrangement']);
        $this->assertSame(26, $plan['chairs']);
        $this->assertSame(8, $plan['tables']);
        $this->assertSame(2, $plan['cables']);
    }
}
