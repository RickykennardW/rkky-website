<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ReservationUpdated extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly string $message) {}

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(User $notifiable): array
    {
        return ['booking_id' => $this->booking->id, 'title' => $this->booking->title, 'message' => $this->message];
    }
}
