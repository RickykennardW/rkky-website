<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_resources_and_room_dimensions_are_validated(): void
    {
        $data = [
            'name' => 'Media studio', 'location' => 'Building A', 'description' => 'Campus recording studio',
            'capacity' => 20, 'chairs' => 20, 'tables_count' => 3, 'active' => '1', 'facilities' => ['Wi-Fi'],
            'layout' => [
                'width' => 8, 'depth' => 7, 'door' => ['wall' => 'south', 'offset' => 1, 'width' => 1.2, 'clearance' => 1.2],
                'blocked' => '[]', 'fixed' => '[]',
            ],
        ];

        $this->actingAs(User::factory()->create())->post('/admin/rooms', $data)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/rooms/new')->assertOk();

        $invalid = $data;
        $invalid['layout']['door']['offset'] = 12;
        $this->post('/admin/rooms', $invalid)->assertSessionHasErrors('layout');
        $this->post('/admin/rooms', $data)->assertRedirect(route('admin.rooms.index'))->assertSessionHasNoErrors();
        $room = Room::query()->where('name', $data['name'])->sole();
        $this->assertSame('Wi-Fi', $room->facilities()->sole()->facility);
        $this->post(route('admin.rooms.save', $room), [...$data, 'active' => '0'])->assertRedirect();
        $this->assertFalse($room->fresh()->active);

        $item = [
            'name' => 'Audio recorder', 'category' => 'Audio', 'description' => 'Portable recording unit',
            'total_quantity' => 5, 'unusable_quantity' => 1, 'status' => 'available',
        ];
        $this->post('/admin/equipment', $item)->assertRedirect(route('admin.equipment.index'))->assertSessionHasNoErrors();
        $equipment = Equipment::query()->where('name', $item['name'])->sole();
        $this->assertSame(4, $equipment->usableQuantity());
        $this->get(route('admin.equipment.edit', $equipment))->assertOk();
        $this->post(route('admin.equipment.save', $equipment), [...$item, 'status' => 'maintenance'])->assertRedirect();
        $this->assertSame('maintenance', $equipment->fresh()->status);
    }
}
