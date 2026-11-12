@extends('layouts.shell')
@section('title', 'Rooms')

@section('content')
<x-heading title="Campus rooms" description="Find a room by capacity, facilities, or reservation period." />
<form method="get" action="{{ route('rooms.index') }}" class="panel grid gap-4 p-4 sm:p-5">
    <div class="flex flex-wrap items-center gap-2 text-sm font-medium"><x-icon name="calendar-range" class="size-4 text-accent" />Check a reservation period <span class="ml-auto text-xs text-muted">Asia/Jakarta · WIB</span></div>
    <x-period-fields :period="$filter" />
    <div class="flex flex-wrap items-end gap-3">
        <label class="label w-38">Minimum capacity<input class="input" type="number" min="1" max="500" name="capacity" placeholder="Any" value="{{ $filter['capacity'] ?? '' }}"></label>
        <fieldset class="grid min-w-0 gap-2">
            <legend class="mb-2 text-sm font-medium">Facilities</legend>
            <div class="flex flex-wrap gap-2">
                @foreach($facilities as $facility)
                    <label class="chip min-h-9 cursor-pointer"><input class="checkbox" type="checkbox" name="facility[]" value="{{ $facility }}" @checked(in_array($facility, $filter['facility'] ?? [], true))>{{ $facility }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="ml-auto flex flex-wrap gap-2"><a href="{{ route('rooms.index') }}" class="button button-ghost">Clear</a><button class="button button-primary"><x-icon name="search" class="size-4" /> Apply filters</button></div>
    </div>
</form>
<div class="flex items-center justify-between gap-2">
    <h2 class="section-title">{{ $rooms->total() }} {{ Str::plural('room', $rooms->total()) }}</h2>
    @if($availability !== null)<span class="text-xs text-muted">Available for the selected period</span>@endif
</div>
@if($rooms->count())
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($rooms as $room)<x-room-card :room="$room" :available="$availability[$room->id] ?? null" :period="array_filter($filter)" />@endforeach
    </div>
    {{ $rooms->links() }}
@else
    <x-empty icon="building-2" title="No rooms match these filters" description="Try a different time, lower capacity, or fewer facilities." :href="route('rooms.index')" action="Clear filters" />
@endif
@endsection
