<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCatalogSeeder::class);
    }

    public function test_student_can_browse_check_availability_book_and_cancel_only_their_own_request(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $room = $this->room();
        $data = $this->requestData($room);

        $this->actingAs($student)->get('/')->assertOk()->assertSee($room->name);
        $this->get('/rooms?capacity=50')->assertOk()->assertSee($room->name)->assertDontSee('Classroom B');
        $this->get(route('rooms.show', $room))->assertOk()->assertSee('Room calendar');
        $this->get('/equipment')->assertOk()->assertSee('Portable Projector');
        $this->getJson($this->availabilityUrl($data))->assertOk()->assertJsonPath('rooms.'.$room->id, true);

        $this->post('/bookings', $data)->assertRedirect();
        $booking = Booking::query()->ownedBy($student)->sole();
        $this->assertSame('pending', $booking->status);
        $this->get('/bookings')->assertOk()->assertSee($booking->title);

        $this->actingAs($other)->get(route('bookings.show', $booking))->assertNotFound();
        $this->post(route('bookings.cancel', $booking))->assertNotFound();
        $this->actingAs($student)->post(route('bookings.cancel', $booking))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_admin_approval_prevents_overlapping_reservations_and_rejection_notifies_the_owner(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $data = $this->requestData($this->room());

        $this->actingAs($student)->post('/bookings', [...$data, 'title' => 'First event'])->assertRedirect();
        $this->post('/bookings', [...$data, 'title' => 'Overlapping event'])->assertRedirect();
        $first = Booking::query()->where('title', 'First event')->sole();
        $second = Booking::query()->where('title', 'Overlapping event')->sole();

        $this->post(route('admin.bookings.review', $first), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('First event');
        $this->post(route('admin.bookings.review', $first), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $first->fresh()->status);
        $this->post(route('admin.bookings.review', $second), ['decision' => 'approve'])->assertSessionHasErrors('operation');
        $this->assertSame('pending', $second->fresh()->status);

        $this->getJson($this->availabilityUrl($data))->assertOk()->assertJsonPath('rooms.'.$first->room_id, false);
        $this->post(route('admin.bookings.review', $second), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->post(route('admin.bookings.review', $second), [
            'decision' => 'reject', 'reason' => 'The room is already allocated.',
        ])->assertRedirect();
        $this->assertSame('rejected', $second->fresh()->status);
        $this->assertSame('The room is already allocated.', $second->fresh()->review_note);
        $this->assertCount(2, $student->unreadNotifications);
        $this->actingAs($student)->get('/notifications')->assertOk()->assertSee('Your request was rejected.');
        $this->post('/notifications/read')->assertRedirect();
        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_equipment_only_request_enforces_stock_and_records_pickup_and_return(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::query()->where('name', 'Portable Projector')->sole();
        $data = $this->requestData();

        $this->actingAs($student)->post('/bookings', [...$data, 'quantities' => [$equipment->id => 6]])
            ->assertSessionHasErrors('operation');
        $this->post('/bookings', [...$data, 'quantities' => [$equipment->id => 3]])->assertRedirect();
        $booking = Booking::query()->ownedBy($student)->sole();
        $this->assertNull($booking->room_id);
        $this->assertSame(3, $booking->items()->sole()->quantity);

        $this->actingAs($admin)->post(route('admin.bookings.review', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->getJson($this->availabilityUrl($data))->assertOk()->assertJsonPath('equipment.'.$equipment->id, 2);
        $this->post(route('admin.bookings.pickup', $booking))->assertSessionHasErrors('operation');
        DB::table('bookings')->where('id', $booking->id)->update([
            'starts_at' => DB::raw("now() - interval '30 minutes'"),
            'ends_at' => DB::raw("now() + interval '30 minutes'"),
        ]);

        $this->post(route('admin.bookings.pickup', $booking))->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Record equipment return');

        $return = ['items' => [['equipment_id' => $equipment->id, 'missing' => 2, 'damaged' => 2]], 'note' => 'Inventory inspected.'];
        $this->post(route('admin.bookings.return', $booking), $return)->assertSessionHasErrors('operation');
        $return['items'][0]['missing'] = 1;
        $return['items'][0]['damaged'] = 0;
        $this->post(route('admin.bookings.return', $booking), $return)->assertRedirect()->assertSessionHasNoErrors();

        $loan = EquipmentLoan::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame($admin->id, $loan->picked_up_by);
        $this->assertSame($admin->id, $loan->returned_by);
        $this->assertSame(1, $loan->missing);
        $this->assertSame(1, $equipment->fresh()->unusable_quantity);
        $this->assertSame('completed', $booking->fresh()->status);
        $this->get('/admin/loans?status=returned')->assertOk()->assertSee('Portable Projector');
    }

    public function test_booking_rejects_invalid_time_and_empty_resource_requests(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/bookings', $this->requestData())->assertSessionHasErrors('room_id');
        $this->post('/bookings', [...$this->requestData($this->room()), 'end' => '08:00'])
            ->assertSessionHasErrors('date');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_planner_is_deterministic_and_booking_revalidates_the_attached_plan(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $this->get(route('planner.show', ['room' => $room->id]))->assertOk()->assertSee('Smart Room Planner');
        $input = [
            'room_id' => $room->id, 'eventType' => 'Workshop', 'participants' => 16, 'committee' => 4,
            'presentation' => true, 'registrationDesk' => true, 'discussion' => true,
        ];
        $result = $this->postJson('/planner/preview', $input)->assertOk()
            ->assertJsonPath('plan.chairs', 18)->assertJsonPath('plan.arrangement', 'Group / Cluster')->assertJsonPath('error', null)->json();
        $this->postJson('/planner/preview', $input)->assertExactJson($result);
        $this->assertCount(18, array_filter($result['items'], fn (array $item) => $item['kind'] === 'chair'));

        $data = [...$this->requestData($room), 'participants' => 16, 'committee' => 4, 'planner' => json_encode($result['plan'])];
        $this->post('/bookings', [...$data, 'participants' => 20])->assertSessionHasErrors('planner');
        $this->post('/bookings', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Group / Cluster', Booking::query()->sole()->planner['arrangement']);
    }

    private function room(): Room
    {
        return Room::query()->where('name', 'Seminar Room A')->sole();
    }

    private function requestData(?Room $room = null): array
    {
        $date = now('Asia/Jakarta')->addDays(3)->format('Y-m-d');

        return [
            'room_id' => $room?->id, 'title' => 'Student workshop', 'description' => 'Peer learning session.',
            'date' => $date, 'start' => '09:00', 'end_date' => $date, 'end' => '11:00',
            'participants' => 12, 'committee' => 2,
        ];
    }

    private function availabilityUrl(array $request): string
    {
        return route('availability', Arr::only($request, ['date', 'start', 'end_date', 'end']));
    }
}
