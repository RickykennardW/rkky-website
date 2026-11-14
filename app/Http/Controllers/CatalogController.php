<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Room;
use App\Services\Availability;
use App\Services\BookingPeriod;
use App\Services\SchedulingRules;
use App\Services\SmartScheduling;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private readonly Availability $availability) {}

    public function rooms(Request $request): View
    {
        [$filter, $period] = $this->filters($request, [
            'capacity' => ['nullable', 'integer', 'between:1,1000'],
            'committee' => ['nullable', 'integer', 'between:0,500'],
            ...SchedulingRules::equipmentRules(),
            'facility' => ['nullable', 'array', 'max:6'],
            'facility.*' => ['required', Rule::in(Room::FACILITIES), 'distinct'],
        ]);
        $resources = $period ? $this->availability->resources($request->user(), $period) : null;
        $available = $resources['rooms'] ?? null;
        if ($available !== null && SchedulingRules::equipmentIssues(
            SchedulingRules::equipmentSelection($filter['quantities'] ?? []), $resources['equipment'], [],
        )) {
            $available = [];
        }
        $query = Room::query()->with('facilities')->where('active', true)->orderBy('name')->orderBy('id');

        if (isset($filter['capacity'])) {
            $query->where('capacity', '>=', $filter['capacity']);
        }
        foreach ($filter['facility'] ?? [] as $facility) {
            $query->whereHas('facilities', fn ($query) => $query->where('facility', $facility));
        }
        if ($available !== null) {
            $query->whereIn('id', array_keys(array_filter($available)));
        }

        return view('catalog.rooms', [
            'rooms' => $query->paginate(12)->withQueryString(),
            'filter' => $filter,
            'availability' => $available,
            'facilities' => Room::FACILITIES,
        ]);
    }

    public function room(Request $request, Room $room): View
    {
        $filter = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'], ...BookingPeriod::rules(false), ...SmartScheduling::inputRules(false),
        ]);
        $timezone = config('campusspace.timezone');
        $week = isset($filter['week'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filter['week'], $timezone)
            : CarbonImmutable::today($timezone);

        return view('catalog.room', [
            'room' => $room->load('facilities'),
            'week' => $week,
            'filter' => array_filter($filter),
            'calendar' => $this->availability->week($request->user(), $room, $week),
        ]);
    }

    public function equipment(Request $request): View
    {
        [$filter, $period] = $this->filters($request, [
            'category' => ['nullable', Rule::in(Equipment::CATEGORIES)],
            'available' => ['nullable', 'boolean'],
        ]);
        $available = $period ? $this->availability->resources($request->user(), $period)['equipment'] : null;
        $query = Equipment::query()->orderBy('name')->orderBy('id');

        if (isset($filter['category'])) {
            $query->where('category', $filter['category']);
        }
        if ($request->boolean('available')) {
            $query->where('status', 'available')->whereColumn('total_quantity', '>', 'unusable_quantity');
            if ($available !== null) {
                $query->whereIn('id', array_keys(array_filter($available)));
            }
        }

        return view('catalog.equipment', [
            'equipment' => $query->paginate(12)->withQueryString(),
            'filter' => $filter,
            'availability' => $available,
            'categories' => Equipment::CATEGORIES,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        [$filter, $period] = $this->filters($request, [], true);

        return response()->json($this->availability->resources($request->user(), $period))
            ->header('Cache-Control', 'private, no-store');
    }

    private function filters(Request $request, array $rules, bool $requirePeriod = false): array
    {
        if ($request->filled('endDate') && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('endDate')]);
        }
        if ($request->filled(['date', 'start', 'end']) && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('date')]);
        }

        $hasPeriod = $requirePeriod || $request->anyFilled(['date', 'start', 'end_date', 'end']);
        $filter = $request->validate([...$rules, ...BookingPeriod::rules($hasPeriod)]);

        return [$filter, $hasPeriod ? BookingPeriod::from($filter) : null];
    }
}
