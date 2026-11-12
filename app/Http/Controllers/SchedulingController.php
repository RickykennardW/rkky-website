<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Room;
use App\Services\Availability;
use App\Services\BookingPeriod;
use App\Services\DemoWorkspace;
use App\Services\SchedulingRequestText;
use App\Services\SmartScheduling;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchedulingController extends Controller
{
    public function __invoke(Request $request, SmartScheduling $scheduling, SchedulingRequestText $interpreter, DemoWorkspace $demo, Availability $availability): View
    {
        $demoMode = config('campusspace.demo');
        $rooms = $demoMode ? $demo->allRooms() : Room::query()->with('facilities')->orderBy('name')->get();
        $equipment = $demoMode ? $demo->equipment() : Equipment::query()->orderBy('name')->get();
        $fields = [...BookingPeriod::defaults(), 'participants' => 24, 'committee' => 0];
        $example = $demoMode ? $demo->bookings($request)->firstWhere('id', 'demo-approved') : null;
        $result = null;
        $interpreted = false;

        if ($request->isMethod('post')) {
            $action = $request->validate(['action' => ['required', 'in:interpret,check'], 'request_text' => ['nullable', 'string', 'max:500']])['action'];
            if ($action === 'interpret') {
                $text = $request->validate(['request_text' => ['required', 'string', 'max:500']])['request_text'];
                $fields = $interpreter->interpret($text, $rooms, $equipment);
                $interpreted = true;
            } else {
                $fields = $request->validate([
                    ...BookingPeriod::rules(), ...SmartScheduling::inputRules(true),
                    'room' => ['nullable', 'uuid', Rule::in($rooms->pluck('id')->all())],
                ]);
                $requested = BookingPeriod::from($fields, future: true);
                $result = $scheduling->analyze($rooms, $equipment, $requested, $fields, $demoMode
                    ? fn (array $periods) => array_map(fn (BookingPeriod $period) => $demo->availability($request, $period), $periods)
                    : fn (array $periods) => $availability->resourcesFor($request->user(), $periods));
            }
        } elseif ($example && $request->boolean('example')) {
            $start = $example->starts_at->timezone(config('campusspace.timezone'));
            $end = $example->ends_at->timezone(config('campusspace.timezone'));
            if ($start > now()) {
                $fields = [
                    'room' => $example->room_id, 'participants' => 40, 'committee' => 2,
                    'date' => $start->toDateString(), 'start' => $start->format('H:i'),
                    'end_date' => $end->toDateString(), 'end' => $end->format('H:i'),
                    'required_facilities' => ['Projector'],
                ];
            }
        }

        return view('scheduling.index', [
            ...compact('rooms', 'equipment', 'fields', 'result', 'interpreted'),
            'exampleAvailable' => $example && $example->starts_at > now(),
            'requestText' => $request->string('request_text')->limit(500)->toString(),
        ]);
    }
}
