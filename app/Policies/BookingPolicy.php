<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    public function view(User $user, Booking $booking): Response
    {
        return $booking->user_id === $user->id || $user->isAdmin()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cancel(User $user, Booking $booking): Response
    {
        if ($booking->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $booking->status === 'pending'
            ? Response::allow()
            : Response::deny('Only a pending request can be cancelled.');
    }

    public function manage(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }
}
