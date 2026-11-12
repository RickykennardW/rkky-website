@props(['room', 'available' => null, 'period' => []])
@php
    $roomUrl = route('rooms.show', [
        'room' => $room->id,
        ...\Illuminate\Support\Arr::only($period, ['date', 'start', 'end_date', 'end']),
    ]);
@endphp
<article class="panel group flex flex-col overflow-hidden">
    <a href="{{ $roomUrl }}" aria-label="View {{ $room->name }}" class="relative block aspect-[2.2] overflow-hidden bg-raised">
        @if(in_array($room->photo, ['seminar', 'classroom', 'hall'], true))
            <img src="{{ asset('images/rooms/'.$room->photo.'.png') }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.025]">
        @else
            <x-icon name="building-2" class="absolute inset-0 m-auto size-10 text-muted" />
        @endif
        <div class="absolute inset-0 bg-linear-to-t from-[#0b0f14]/55 via-transparent to-transparent"></div>
        <div class="absolute bottom-3 left-3">
            <x-status :status="!$room->active ? 'unavailable' : ($available === null ? 'check schedule' : ($available ? 'available' : 'reserved'))" />
        </div>
    </a>
    <div class="grid flex-1 content-start gap-3 p-4">
        <div><h2 class="font-semibold tracking-tight">{{ $room->name }}</h2>
            <p class="mt-1 flex items-center gap-1.5 text-xs text-muted"><x-icon name="map-pin" class="size-3.5" />{{ $room->location }}</p></div>
        <p class="muted">{{ $room->description }}</p>
        <div class="flex flex-wrap gap-1.5">@foreach($room->facilities as $entry)<span class="chip">{{ $entry->facility }}</span>@endforeach</div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3">
        <span class="flex items-center gap-2 text-xs font-medium text-muted"><x-icon name="users" class="size-4" />{{ $room->capacity }} people</span>
        <a href="{{ $roomUrl }}" class="flex min-h-9 items-center gap-1.5 text-sm font-semibold text-accent hover:text-ink">View room <x-icon name="arrow-right" class="size-4" /></a>
    </div>
</article>
