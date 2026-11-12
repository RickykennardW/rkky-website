<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\ReservationUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Reservations
{
    public function __construct(private readonly DatabaseActor $actor) {}

    public function reserve(User $user, array $details, BookingPeriod $period, array $items, ?array $plan): string
    {
        return $this->actor->run($user, function () use ($details, $period, $items, $plan): string {
            $id = DB::scalar(
                'select public.create_booking(?::uuid, ?, ?, ?::integer, ?::integer, ?::timestamptz, ?::timestamptz, ?::jsonb, ?::jsonb)',
                [
                    $details['room_id'] ?? null, $details['title'], $details['description'],
                    $details['participants'], $details['committee'], ...$period->bindings(),
                    json_encode($items, JSON_THROW_ON_ERROR), $plan === null ? null : json_encode($plan, JSON_THROW_ON_ERROR),
                ],
            );
            if (! empty($details['required_facilities'])) {
                // The advisory lock acquired by create_booking is held until this transaction commits.
                DB::table('public.bookings')->where('id', $id)->update([
                    'required_facilities' => json_encode(array_values($details['required_facilities']), JSON_THROW_ON_ERROR),
                ]);
            }

            return $id;
        });
    }

    public function cancel(User $user, Booking $booking): void
    {
        Gate::forUser($user)->authorize('cancel', $booking);

        $this->actor->run($user, fn () => DB::selectOne('select public.cancel_booking(?::uuid)', [$booking->id]));
    }

    public function review(User $admin, Booking $booking, bool $approved, ?string $reason): void
    {
        Gate::forUser($admin)->authorize('manage', $booking);

        $this->actor->run($admin, function () use ($booking, $approved, $reason): void {
            DB::selectOne('select public.review_booking(?::uuid, ?::boolean, ?)', [$booking->id, $approved, $reason]);
            $this->notifyOwner($booking, $approved ? 'Your request was approved.' : 'Your request was rejected.');
        });
    }

    public function completeRoom(User $admin, Booking $booking): void
    {
        Gate::forUser($admin)->authorize('manage', $booking);

        $this->actor->run($admin, function () use ($booking): void {
            DB::selectOne('select public.complete_room_booking(?::uuid)', [$booking->id]);
            $this->notifyOwner($booking, 'Your reservation was marked complete.');
        });
    }

    private function notifyOwner(Booking $booking, string $message): void
    {
        User::query()->find($booking->user_id)?->notify(new ReservationUpdated($booking, $message));
    }
}
