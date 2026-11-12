<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\User;
use App\Notifications\ReservationUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Inventory
{
    public function __construct(private readonly DatabaseActor $actor) {}

    public function saveRoom(User $admin, ?Room $room, array $data): string
    {
        Gate::forUser($admin)->authorize('admin');

        return $this->actor->run($admin, fn (): string => DB::scalar(
            'select public.save_room(?::uuid, ?, ?, ?, ?::integer, ?::integer, ?::integer, ?::boolean, ?, ?::jsonb, array(select jsonb_array_elements_text(?::jsonb)))',
            [
                $room?->id, $data['name'], $data['location'], $data['description'], $data['capacity'], $data['chairs'],
                $data['tables_count'], $data['active'], $data['photo'] ?? null,
                json_encode($data['layout'], JSON_THROW_ON_ERROR), json_encode($data['facilities'] ?? [], JSON_THROW_ON_ERROR),
            ],
        ));
    }

    public function saveEquipment(User $admin, ?Equipment $equipment, array $data): string
    {
        Gate::forUser($admin)->authorize('admin');

        return $this->actor->run($admin, fn (): string => DB::scalar(
            'select public.save_equipment(?::uuid, ?, ?, ?, ?::integer, ?::integer, ?::public.equipment_status)',
            [
                $equipment?->id, $data['name'], $data['category'], $data['description'],
                $data['total_quantity'], $data['unusable_quantity'], $data['status'],
            ],
        ));
    }

    public function pickup(User $admin, Booking $booking): void
    {
        Gate::forUser($admin)->authorize('manage', $booking);

        $this->actor->run($admin, function () use ($booking): void {
            DB::selectOne('select public.record_pickup(?::uuid)', [$booking->id]);
            User::query()->find($booking->user_id)?->notify(new ReservationUpdated($booking, 'Equipment pickup was recorded.'));
        });
    }

    public function returnItems(User $admin, Booking $booking, array $items, ?string $note): bool
    {
        Gate::forUser($admin)->authorize('manage', $booking);

        return $this->actor->run($admin, function () use ($booking, $items, $note): bool {
            $warning = DB::scalar('select public.record_return(?::uuid, ?::jsonb, ?)', [
                $booking->id, json_encode($items, JSON_THROW_ON_ERROR), $note,
            ]);
            User::query()->find($booking->user_id)?->notify(new ReservationUpdated($booking, 'Equipment return was recorded.'));

            return (bool) $warning;
        });
    }
}
