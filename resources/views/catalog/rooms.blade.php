@extends('layouts.shell')
@section('title', 'Cari Ruangan')

@section('content')
<x-heading title="Cari Ruangan" description="Pilih jadwal dan jumlah peserta untuk melihat ruangan yang sesuai.">
    <a href="{{ route('monitoring.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-accent hover:underline">
        <x-icon name="clock-3" class="size-4" /> Lihat status ruangan
    </a>
</x-heading>
<section class="panel grid gap-4 p-4 sm:p-6" aria-label="Jadwal dan kebutuhan">
    <x-room-search :filter="$filter" :facilities="$facilities" />
</section>
<section class="grid min-w-0 gap-4" aria-labelledby="room-results">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="room-results" class="section-title">{{ $rooms->total() }} ruangan{{ $availability !== null ? ' tersedia' : '' }}</h2>
        @if($availability !== null)<span class="text-sm text-muted">{{ $filter['start'] }}–{{ $filter['end'] }} WIB</span>@endif
    </div>
    @if($rooms->count())
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($rooms as $room)<x-room-card :room="$room" :available="$availability[$room->id] ?? null" :period="array_filter($filter)" />@endforeach
        </div>
        {{ $rooms->links() }}
    @else
        @php
            $attendance = (int) ($filter['capacity'] ?? 1);
            $committee = min($attendance - 1, max($filter['committee'] ?? 0, $attendance - 500));
        @endphp
        <x-empty icon="building-2" title="Belum ada ruangan yang cocok" description="Ubah jam, jumlah peserta, atau fasilitas untuk melihat pilihan lain." :href="$availability === null ? null : route('scheduling.index', ['alternatives' => 1, ...Arr::only($filter, ['date', 'start', 'end_date', 'end', 'quantities']), 'participants' => $attendance - $committee, 'committee' => $committee, 'required_facilities' => $filter['facility'] ?? []])" action="Lihat waktu lain" />
    @endif
</section>
@endsection
