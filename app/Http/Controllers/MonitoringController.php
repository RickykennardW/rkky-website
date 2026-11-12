<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Services\DemoWorkspace;
use App\Services\RoomMonitoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(): View
    {
        return view('monitoring.index');
    }

    public function snapshot(Request $request, RoomMonitoring $monitor, DemoWorkspace $demo): JsonResponse
    {
        $now = now()->toImmutable();
        $admin = $request->user()->can('admin');
        if (config('campusspace.demo')) {
            $rooms = $demo->allRooms();
            $bookings = $demo->bookings($request)->whereNotNull('room_id');
        } else {
            $rooms = Room::query()->orderBy('name')->get(['id', 'name', 'location', 'capacity', 'active']);
            $bookings = Booking::query()->whereNotNull('room_id')->where('status', 'approved')->where('ends_at', '>', $now->toIso8601String())
                ->get(['room_id', 'status', 'starts_at', 'ends_at', ...($admin ? ['title'] : [])]);
        }

        return response()->json($monitor->snapshot($rooms, $bookings, $now, $admin))
            ->header('Cache-Control', 'private, no-store');
    }

    public function sample(Request $request, DemoWorkspace $demo): RedirectResponse
    {
        abort_unless(config('campusspace.demo'), 404);
        $demo->loadActiveSample($request);

        return redirect()->route('monitoring.index')->with('status', 'In-progress sample loaded for this demo session.');
    }
}
