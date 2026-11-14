<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchedulingMonitoringTest extends TestCase
{
    use DatabaseTransactions;

    public function test_scheduling_ranks_valid_rooms_and_nearby_times_using_postgres_availability(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $student = User::factory()->create();
        $room = Room::query()->where('name', 'Seminar Room A')->firstOrFail();
        $projector = Equipment::query()->where('name', 'Portable Projector')->firstOrFail();
        $day = now('Asia/Jakarta')->toImmutable()->addDays(3)->startOfDay();
        $approved = $this->booking($student, $room, $day->setTime(13, 0), $day->setTime(15, 0), 'approved');
        $alternative = Room::query()->where('name', 'Multipurpose Hall C')->firstOrFail();
        foreach (['pending', 'rejected', 'cancelled'] as $status) {
            $this->booking($student, $alternative, $day->setTime(13, 0), $day->setTime(15, 0), $status);
        }

        $input = [
            'action' => 'check', 'room' => $room->id, 'participants' => 40, 'committee' => 2,
            'required_facilities' => ['Projector'], 'date' => $day->toDateString(),
            'end_date' => $day->toDateString(), 'start' => '13:00', 'end' => '15:00',
        ];
        $response = $this->actingAs($student)->post(route('scheduling.check'), $input)
            ->assertOk()->assertSee('Ruangan sudah digunakan pada jam tersebut.')
            ->assertSee('Kapasitas 36 orang kurang untuk 42 orang.')->assertSee('Fasilitas tidak tersedia');
        $result = $response->viewData('result');
        $this->assertSame($alternative->id, $result['sameTime']->first()['room']->id);
        $this->assertSame('15:00', $result['preferredTimes']->first()['period']->startsAt->format('H:i'));
        $this->assertSame(['Projector'], $result['sameTime']->first()['booking']['required_facilities']);

        DB::table('booking_equipment')->insert(['booking_id' => $approved->id, 'equipment_id' => $projector->id, 'quantity' => 5]);
        $this->post(route('scheduling.check'), [...$input, 'quantities' => [$projector->id => 1]])
            ->assertOk()->assertSee('0 tersedia, 1 diminta');
    }

    public function test_missing_required_facility_is_rechecked_at_request_and_approval(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = Room::query()->where('name', 'Seminar Room A')->firstOrFail();
        $day = now('Asia/Jakarta')->addDays(3)->toDateString();
        $payload = [
            'room_id' => $room->id, 'title' => 'Projector workshop', 'description' => 'Student workshop',
            'participants' => 20, 'committee' => 2, 'date' => $day, 'end_date' => $day, 'start' => '13:00', 'end' => '15:00',
        ];
        $this->actingAs($student)->post(route('bookings.store'), [...$payload, 'required_facilities' => ['Whiteboard']])
            ->assertSessionHasErrors('operation');
        $this->assertDatabaseMissing('bookings', ['title' => $payload['title']]);
        $this->post(route('bookings.store'), [...$payload, 'required_facilities' => ['Projector']])->assertRedirect();
        $booking = Booking::query()->where('title', $payload['title'])->firstOrFail();
        $this->assertSame(['Projector'], $booking->required_facilities);

        DB::table('room_facilities')->where('room_id', $room->id)->where('facility', 'Projector')->delete();
        $this->actingAs($admin)->post(route('admin.bookings.review', $booking), ['decision' => 'approve'])
            ->assertSessionHasErrors('operation');
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_monitoring_handles_back_to_back_times_and_hides_titles_from_students(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $student = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = Room::query()->where('name', 'Seminar Room A')->firstOrFail();
        $futureRoom = Room::query()->where('name', 'Classroom B')->firstOrFail();
        $now = now('Asia/Jakarta')->startOfMinute();
        $this->travelTo($now);
        $this->booking($owner, $room, $now->copy()->subHour(), $now, 'approved', 'Expired workshop');
        $this->booking($owner, $room, $now, $now->copy()->addHour(), 'approved', 'Private research group');
        $this->booking($owner, $futureRoom, $now->copy()->addHour(), $now->copy()->addHours(2), 'approved', 'Future workshop');

        $this->getJson(route('monitoring.snapshot'))->assertUnauthorized();
        $response = $this->actingAs($student)->getJson(route('monitoring.snapshot'))->assertOk()
            ->assertJsonPath('timezone', 'Asia/Jakarta')->assertJsonPath('server_time', $now->getTimestampMs())
            ->assertJsonPath('summary.in_use', 1)->assertJsonPath('summary.upcoming', 1)
            ->assertDontSee('Private research group')->assertDontSee($owner->email)->assertDontSee('Expired workshop')->assertDontSee('/admin/bookings/');
        $cards = collect($response->json('rooms'))->keyBy('id');
        $this->assertSame('in use', $cards[$room->id]['status']);
        $this->assertSame($now->getTimestampMs(), $cards[$room->id]['current']['start']);
        $this->assertArrayNotHasKey('title', $cards[$room->id]['current']);
        $this->assertSame('upcoming', $cards[$futureRoom->id]['status']);
        $this->assertTrue($cards->contains('status', 'available'));
        $this->post('/monitoring/sample')->assertNotFound();

        $this->actingAs($admin)->getJson(route('monitoring.snapshot'))->assertOk()
            ->assertSee('Private research group')->assertDontSee($owner->email);
    }

    private function booking(User $owner, Room $room, $start, $end, string $status, string $title = 'Test reservation'): Booking
    {
        $id = Str::uuid()->toString();
        DB::table('bookings')->insert([
            'id' => $id, 'user_id' => $owner->id, 'room_id' => $room->id,
            'title' => $title, 'description' => 'Schedule fixture', 'participants' => 12, 'committee' => 0,
            'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'status' => $status,
        ]);

        return Booking::query()->findOrFail($id);
    }
}
