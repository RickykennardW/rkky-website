<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return view('dashboard', ['user' => null]);
        }
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $bookings = Booking::query()->ownedBy($user);

        return view('dashboard', [
            'user' => $user,
            'rooms' => Room::query()->with('facilities')->where('active', true)->orderBy('name')->limit(3)->get(),
            'latestBooking' => $bookings->with('room')->latest()->orderByDesc('id')->first(),
        ]);
    }
}
