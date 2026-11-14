<?php

namespace App\Http\Controllers;

use App\Models\Booking;
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

        $now = now()->toIso8601String();
        $bookings = Booking::query()->ownedBy($user)->with('room')
            ->orderByRaw(<<<'SQL'
                case
                    when status = 'approved' and starts_at <= ? and ends_at > ? then 0
                    when status in ('approved', 'pending') and starts_at > ? then 1
                    else 2
                end,
                case
                    when (status = 'approved' and ends_at > ?) or (status = 'pending' and starts_at > ?) then starts_at
                end
                SQL, [$now, $now, $now, $now, $now])
            ->latest()->orderByDesc('id')->limit(3)->get();

        return view('dashboard', [
            'user' => $user,
            'bookings' => $bookings,
        ]);
    }
}
