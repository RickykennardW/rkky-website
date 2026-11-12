<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\DemoWorkspace;
use App\Services\SmartRoomPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlannerController extends Controller
{
    public function show(Request $request, SmartRoomPlanner $planner): View
    {
        $selected = $request->validate([
            'room' => ['nullable', 'uuid'],
            'participants' => ['nullable', 'integer', 'between:1,500'],
            'committee' => ['nullable', 'integer', 'between:0,500'],
        ]);
        $rooms = config('campusspace.demo')
            ? app(DemoWorkspace::class)->rooms()
            : Room::query()->where('active', true)->orderBy('name')->orderBy('id')->get();
        $room = $rooms->firstWhere('id', $selected['room'] ?? null) ?? $rooms->first();
        $input = array_replace(SmartRoomPlanner::DEFAULTS, array_intersect_key(array_filter($selected, fn ($value) => $value !== null), SmartRoomPlanner::DEFAULTS));
        $plan = $planner->estimate($input);

        return view('planner.show', [
            'rooms' => $rooms,
            'room' => $room,
            'result' => $room ? $planner->generate($room, $plan) : null,
            'events' => SmartRoomPlanner::EVENTS,
            'arrangements' => SmartRoomPlanner::ARRANGEMENTS,
        ]);
    }

    public function preview(Request $request, SmartRoomPlanner $planner): JsonResponse
    {
        $data = $request->validate(['room_id' => ['required', 'uuid'], ...SmartRoomPlanner::rules()]);
        $room = config('campusspace.demo')
            ? app(DemoWorkspace::class)->rooms()->firstWhere('id', $data['room_id'])
            : Room::query()->where('active', true)->findOrFail($data['room_id']);
        abort_if($room === null, 404);

        return response()->json($planner->generate($room, $planner->estimate($data)))
            ->header('Cache-Control', 'private, no-store');
    }
}
