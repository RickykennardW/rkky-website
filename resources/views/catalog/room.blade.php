@extends('layouts.shell')
@section('title', 'Room details')

@section('content')
<x-breadcrumb :href="route('rooms.index')" label="Campus rooms" :current="$room->name" />
<x-heading :title="$room->name" :description="$room->location">
    @if($room->active)
        <a href="{{ route('planner.show', ['room' => $room->id]) }}" class="button button-secondary"><x-icon name="grid-2x2" class="size-4" /> Room planner</a>
        <a href="{{ route('bookings.create', ['room' => $room->id, ...Arr::only($filter, ['date', 'start', 'end_date', 'end'])]) }}" class="button button-primary"><x-icon name="calendar-plus" class="size-4" /> Book this room</a>
    @endif
</x-heading>
<div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
    <section class="panel overflow-hidden">
        @if(in_array($room->photo, ['seminar', 'classroom', 'hall'], true))
            <div class="relative aspect-[2.1] bg-raised"><img src="{{ asset('images/rooms/'.$room->photo.'.png') }}" alt="Illustrative view of {{ $room->name }}" class="absolute inset-0 size-full object-cover"></div>
        @endif
        <div class="grid gap-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="section-title">Room information</h2><x-status :status="$room->active ? 'check schedule' : 'unavailable'" /></div>
            <p class="muted">{{ $room->description }}</p>
            <div class="flex flex-wrap gap-2">
                <span class="chip"><x-icon name="users" class="size-3.5" />{{ $room->capacity }} people</span>
                <span class="chip"><x-icon name="armchair" class="size-3.5" />{{ $room->chairs }} chairs</span>
                <span class="chip"><x-icon name="table-2" class="size-3.5" />{{ $room->tables_count }} tables</span>
            </div>
            <div class="flex flex-wrap gap-2">@foreach($room->facilities as $entry)<span class="chip">{{ $entry->facility }}</span>@endforeach</div>
        </div>
    </section>
    <section class="panel grid content-start gap-4 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title flex items-center gap-2"><x-icon name="calendar-days" class="text-accent" /> Room calendar</h2>
            <div class="flex gap-2">
                <a href="{{ route('rooms.show', [$room, ...$filter, 'week' => $week->subWeek()->format('Y-m-d')]) }}" aria-label="Previous week" class="button button-ghost px-2"><x-icon name="chevron-left" class="size-4" /></a>
                <a href="{{ route('rooms.show', [$room, ...$filter, 'week' => $week->addWeek()->format('Y-m-d')]) }}" aria-label="Next week" class="button button-ghost px-2"><x-icon name="chevron-right" class="size-4" /></a>
            </div>
        </div>
        <p class="text-xs text-muted">Approved reservation windows · Asia/Jakarta</p>
        <ul class="grid gap-2">
            @foreach($calendar as $day)
                <li class="grid gap-1 rounded-lg border border-line bg-[#0e141c] px-3 py-2 text-xs sm:grid-cols-[100px_1fr]">
                    <strong class="font-medium">{{ $day['date']->format('D, d M') }}</strong>
                    <span class="text-muted">{{ !$room->active ? 'Unavailable' : ($day['occupied'] ? implode(', ', $day['occupied']) : 'No approved reservations') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
