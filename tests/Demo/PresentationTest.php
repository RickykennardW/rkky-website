<?php

namespace Tests\Demo;

use App\Services\BookingPeriod;
use App\Services\DemoWorkspace;
use App\Services\SmartRoomPlanner;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class PresentationTest extends TestCase
{
    public function test_guest_and_student_can_use_the_catalog_calendar_and_planner(): void
    {
        $this->assertTrue(config('campusspace.demo'));
        $this->assertSame([], config('database.connections'));
        $this->assertSame([], config('campusspace.legacy_auth'));
        $room = app(DemoWorkspace::class)->rooms()->first();

        $this->get('/')->assertOk()->assertSee('Mode demo');
        $this->get('/login')->assertOk()->assertSee('Masuk sebagai mahasiswa');
        $this->get('/admin')->assertRedirect(route('login'));
        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();

        $this->get('/')->assertOk()->assertSee('Demo Student')->assertSee('Peminjaman cepat')
            ->assertSee('Kamu belum punya peminjaman.')->assertSee(route('bookings.create'))
            ->assertDontSee('x-data="roomMonitoring"', false);
        $this->get('/rooms')->assertOk()->assertSee($room->name);
        $period = BookingPeriod::defaults();
        $this->get(route('rooms.index', [...Arr::except($period, 'end_date'), 'capacity' => 40]))
            ->assertOk()->assertSee('2 ruangan tersedia')->assertDontSee('Classroom B')
            ->assertSee(route('bookings.create', ['room' => $room->id, ...$period, 'participants' => 40, 'committee' => 0]));
        $this->get(route('rooms.show', $room))->assertOk()->assertSee('Kalender ruangan')->assertSee('13:00–15:00');
        $this->get('/equipment')->assertOk()->assertSee('Portable Projector');
        $this->get('/planner')->assertOk()->assertSee('Bantu Atur Ruangan');
        $this->postJson('/planner/preview', [...SmartRoomPlanner::DEFAULTS, 'room_id' => $room->id])
            ->assertOk()->assertJsonStructure(['plan' => ['chairs', 'tables', 'projectors']]);
        $this->get('/admin')->assertForbidden();
    }

    public function test_demo_bookings_and_admin_decisions_are_session_scoped(): void
    {
        $demo = app(DemoWorkspace::class);
        $room = $demo->rooms()->first();
        $item = $demo->equipment()->first();
        $period = BookingPeriod::defaults();
        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();

        $this->get('/book')->assertOk();
        $this->getJson('/api/availability?'.http_build_query($period))
            ->assertOk()->assertJsonPath('rooms.'.$room->id, true);
        $details = [...$period, 'description' => 'Presentation simulation', 'participants' => 20, 'committee' => 2];
        $roomResponse = $this->post('/bookings', [
            ...Arr::except($details, 'end_date'), 'title' => 'Demo room request', 'room_id' => $room->id,
        ])->assertRedirect();
        $equipmentResponse = $this->post('/bookings', [...$details, 'title' => 'Demo equipment request', 'quantities' => [$item->id => 2]])->assertRedirect();
        $roomId = basename($roomResponse->headers->get('Location'));
        $equipmentId = basename($equipmentResponse->headers->get('Location'));
        $this->get('/bookings/'.$roomId)->assertOk()->assertSee('Menunggu Persetujuan');
        $this->get('/')->assertOk()->assertSee(route('bookings.show', $roomId))->assertSee('Menunggu Persetujuan');
        $this->post('/admin/bookings/'.$roomId.'/review', ['decision' => 'approve'])->assertForbidden();

        $this->post('/login', ['role' => 'admin'])->assertRedirect('/admin');
        Auth::forgetGuards();
        $this->get('/admin')->assertOk()->assertSee('Demo room request');
        $this->get('/admin/bookings/'.$roomId)->assertOk()->assertSee('Setujui pengajuan');
        $this->post('/admin/bookings/'.$roomId.'/review', ['decision' => 'approve'])->assertRedirect();
        $this->post('/admin/bookings/'.$equipmentId.'/review', ['decision' => 'reject', 'reason' => 'Reschedule the event'])->assertRedirect();
        $this->get('/admin/bookings/'.$roomId)->assertOk()->assertSee('Disetujui');
        $this->get('/admin/bookings/'.$equipmentId)->assertOk()->assertSee('Reschedule the event')->assertSee('Ditolak');
        $this->getJson('/api/availability?'.http_build_query($period))
            ->assertOk()->assertJsonPath('rooms.'.$room->id, false);

        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();
        $this->get('/')->assertOk()->assertSee('Disetujui')->assertSee('Ditolak')
            ->assertSee(route('bookings.show', $roomId))->assertSee(route('bookings.show', $equipmentId));

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['role' => 'student'])->assertRedirect('/');
        Auth::forgetGuards();
        $this->get('/bookings/'.$roomId)->assertNotFound();
    }
}
