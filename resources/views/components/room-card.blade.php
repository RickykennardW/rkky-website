@props(['room', 'available' => null, 'period' => [], 'live' => false])
@php
    $dates = Arr::only($period, ['date', 'start', 'end_date', 'end']);
    $attendance = (int) ($period['capacity'] ?? 1);
    $committee = min($attendance - 1, max($period['committee'] ?? 0, $attendance - 500));
    $selection = [
        'room' => $room->id, ...$dates,
        'participants' => $attendance - $committee, 'committee' => $committee,
        'required_facilities' => $period['facility'] ?? [], 'quantities' => array_filter($period['quantities'] ?? []),
    ];
    $roomUrl = route('rooms.show', $selection);
    $bookingUrl = route('bookings.create', $selection);
@endphp
<article class="panel flex min-w-0 flex-col overflow-hidden">
    <div class="relative flex aspect-[2.35] items-end overflow-hidden bg-raised">
        @if(in_array($room->photo, ['seminar', 'classroom', 'hall'], true))
            <img src="{{ asset('images/rooms/'.$room->photo.'.png') }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
        @else
            <x-icon name="building-2" class="absolute inset-0 m-auto size-10 text-muted" />
        @endif
        @if($available !== null)
            <x-status :status="$available ? 'available' : 'reserved'" class="relative m-3" />
        @elseif($live)
            <template x-if="!error && snapshot?.rooms.some(room => room.id === @js($room->id))">
                <span class="relative m-3 rounded-full px-2.5 py-1 text-xs font-semibold" x-bind:class="badgeClass(snapshot.rooms.find(room => room.id === @js($room->id)))" x-text="statusLabel(snapshot.rooms.find(room => room.id === @js($room->id)))"></span>
            </template>
        @endif
    </div>
    <div class="grid flex-1 content-start gap-3 p-4">
        <div>
            <h3 class="text-base font-semibold tracking-tight">{{ $room->name }}</h3>
            <p class="mt-1 text-sm text-muted">{{ $room->location }}</p>
        </div>
        <p class="flex items-center gap-2 text-sm text-muted"><x-icon name="users" class="size-4" />Kapasitas {{ $room->capacity }} orang</p>
        @if($room->facilities->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">@foreach($room->facilities->take(3) as $entry)<span class="chip">{{ __('campusspace.facilities.'.$entry->facility) }}</span>@endforeach</div>
        @endif
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-2.5">
        <a href="{{ $roomUrl }}" class="inline-flex min-h-11 items-center text-sm font-medium text-muted hover:text-ink hover:underline" aria-label="Detail {{ $room->name }}">Detail</a>
        <a href="{{ $bookingUrl }}" class="button {{ $available ? 'button-primary' : 'button-secondary' }}" aria-label="Pilih {{ $room->name }}">Pilih ruangan <x-icon name="arrow-right" class="size-4" /></a>
    </div>
</article>
