<?php

namespace Tests\Demo;

use App\Services\DemoWorkspace;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;

class SchedulingMonitoringTest extends TestCase
{
    public function test_conflict_example_and_monitoring_work_without_any_database_connection(): void
    {
        $this->assertSame([], config('database.connections'));
        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();
        $this->get('/')->assertOk()->assertSee('Live Room Monitoring');
        $example = $this->get('/smart-scheduling?example=1')->assertOk()->viewData('fields');
        $result = $this->post('/smart-scheduling', [...$example, 'action' => 'check'])->assertOk()
            ->assertSee('An approved reservation overlaps this time.')->assertSee('Other times for your preferred room')
            ->viewData('result');
        $this->assertSame('Multipurpose Hall C', $result['sameTime']->first()['room']->name);
        $booking = $result['sameTime']->first()['booking'];
        $this->get(route('bookings.create', $booking))->assertOk()->assertSee('Required room facilities')->assertSee('value="40"', false);

        $fields = $this->post('/smart-scheduling', [
            'action' => 'interpret', 'request_text' => '40 people tomorrow from 13.00 to 15.00 with a projector',
        ])->assertOk()->assertSee('Review the interpreted details')->viewData('fields');
        $projector = app(DemoWorkspace::class)->equipment()->firstWhere('name', 'Portable Projector');
        $this->assertSame([$projector->id => 1], $fields['quantities']);
        $this->assertSame('13:00', $fields['start']);

        $this->post('/monitoring/sample')->assertRedirect('/monitoring');
        $this->getJson('/api/monitoring')->assertOk()
            ->assertJsonPath('summary.in_use', 1)->assertJsonPath('summary.unavailable', 1)
            ->assertDontSee('Campus orientation rehearsal')->assertDontSee('@campusspace.example');
        $this->get('/admin')->assertForbidden();

        $this->post('/login', ['role' => 'admin'])->assertRedirect('/admin');
        Auth::forgetGuards();
        $this->getJson('/api/monitoring')->assertOk()->assertSee('Campus orientation rehearsal')->assertDontSee('@campusspace.example');
        $this->get('/admin/rooms')->assertNotFound();
    }

    public function test_demo_approval_rejects_a_request_after_another_request_takes_the_slot(): void
    {
        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();
        $room = app(DemoWorkspace::class)->rooms()->first();
        $day = now('Asia/Jakarta')->addDays(5)->toDateString();
        $payload = [
            'room_id' => $room->id, 'title' => 'Shared room slot', 'description' => 'A demo workshop',
            'participants' => 20, 'committee' => 2, 'date' => $day, 'end_date' => $day,
            'start' => '13:00', 'end' => '15:00', 'required_facilities' => ['Projector'],
        ];
        $first = basename($this->post('/bookings', $payload)->assertRedirect()->headers->get('Location'));
        $second = basename($this->post('/bookings', $payload)->assertRedirect()->headers->get('Location'));
        $this->post('/login', ['role' => 'admin'])->assertRedirect('/admin');
        Auth::forgetGuards();
        $this->post("/admin/bookings/{$first}/review", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->post("/admin/bookings/{$second}/review", ['decision' => 'approve'])->assertSessionHasErrors('room_id');
        $this->get("/admin/bookings/{$second}")->assertOk()->assertSee('pending');
    }
}
