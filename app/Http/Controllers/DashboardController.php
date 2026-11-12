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
        $upcoming = (clone $bookings)->where('status', 'approved')->where('ends_at', '>', now());

        return view('dashboard', [
            'user' => $user,
            'rooms' => Room::query()->with('facilities')->where('active', true)->orderBy('name')->limit(3)->get(),
            'pendingCount' => (clone $bookings)->where('status', 'pending')->count(),
            'approvedCount' => (clone $bookings)->where('status', 'approved')->count(),
            'upcomingCount' => (clone $upcoming)->count(),
            'upcoming' => $upcoming->with('room')->orderBy('starts_at')->orderBy('id')->limit(5)->get(),
        ]);
    }
}
