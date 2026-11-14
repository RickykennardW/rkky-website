@extends('layouts.shell')
@section('title', 'Detail Ruangan')

@section('content')
@php
    $dates = Arr::only($filter, ['date', 'start', 'end_date', 'end']);
    $search = isset($filter['participants']) ? ['capacity' => $filter['participants'] + ($filter['committee'] ?? 0), 'committee' => $filter['committee'] ?? 0] : [];
@endphp
<x-breadcrumb :href="route('rooms.index', [...$dates, ...$search, 'facility' => $filter['required_facilities'] ?? [], 'quantities' => $filter['quantities'] ?? []])" label="Cari Ruangan" :current="$room->name" />
<x-heading :title="$room->name" :description="$room->location">
    @if($room->active)
        <a href="{{ route('bookings.create', ['room' => $room->id, ...Arr::except($filter, 'week')]) }}" class="button button-primary"><x-icon name="calendar-plus" class="size-4" /> Pilih ruangan</a>
    @endif
</x-heading>
<div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
    <section class="panel overflow-hidden">
        @if(in_array($room->photo, ['seminar', 'classroom', 'hall'], true))
            <div class="relative aspect-[2.1] bg-raised"><img src="{{ asset('images/rooms/'.$room->photo.'.png') }}" alt="Foto ilustrasi {{ $room->name }}" class="absolute inset-0 size-full object-cover"></div>
        @endif
        <div class="grid gap-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="section-title">Fasilitas ruangan</h2><x-status :status="$room->active ? 'check schedule' : 'unavailable'" /></div>
            <p class="muted">{{ $room->description }}</p>
            <div class="flex flex-wrap gap-2">
                <span class="chip"><x-icon name="users" class="size-3.5" />{{ $room->capacity }} orang</span>
            </div>
            <div class="flex flex-wrap gap-2">@foreach($room->facilities as $entry)<span class="chip">{{ __('campusspace.facilities.'.$entry->facility) }}</span>@endforeach</div>
            <details>
                <summary class="w-fit py-1.5 text-sm text-accent">Lihat detail penataan</summary>
                <p class="muted mt-2">{{ $room->chairs }} kursi · {{ $room->tables_count }} meja</p>
                @if($room->active)<a href="{{ route('planner.show', ['room' => $room->id, ...Arr::only($filter, ['participants', 'committee'])]) }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-accent hover:underline">Atur denah dan perlengkapan</a>@endif
            </details>
        </div>
    </section>
    <section class="panel grid content-start gap-4 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title flex items-center gap-2"><x-icon name="calendar-days" class="text-accent" /> Kalender ruangan</h2>
            <div class="flex gap-2">
                <a href="{{ route('rooms.show', [$room, ...$filter, 'week' => $week->subWeek()->format('Y-m-d')]) }}" aria-label="Minggu sebelumnya" class="button button-ghost px-2"><x-icon name="chevron-left" class="size-4" /></a>
                <a href="{{ route('rooms.show', [$room, ...$filter, 'week' => $week->addWeek()->format('Y-m-d')]) }}" aria-label="Minggu berikutnya" class="button button-ghost px-2"><x-icon name="chevron-right" class="size-4" /></a>
            </div>
        </div>
        <p class="text-xs text-muted">Jadwal yang sudah disetujui · WIB</p>
        <ul class="grid gap-2">
            @foreach($calendar as $day)
                <li class="grid gap-1 rounded-lg border border-line bg-[#0e141c] px-3 py-2 text-xs sm:grid-cols-[100px_1fr]">
                    <strong class="font-medium">{{ $day['date']->locale('id')->translatedFormat('D, d M') }}</strong>
                    <span class="text-muted">{{ !$room->active ? 'Tidak tersedia' : ($day['occupied'] ? implode(', ', $day['occupied']) : 'Belum ada peminjaman') }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
