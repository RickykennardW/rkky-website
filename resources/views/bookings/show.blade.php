@extends('layouts.shell')
@section('title', $admin ? 'Tinjau Peminjaman' : 'Detail Peminjaman')

@section('content')
<x-breadcrumb :href="route($admin ? 'admin.bookings.index' : 'bookings.index')" :label="$admin ? 'Permintaan Peminjaman' : 'Peminjaman Saya'" current="Detail" />
<x-heading :title="$booking->title" :description="$booking->period_label"><x-status :status="$booking->status" /></x-heading>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
    <section class="panel grid content-start gap-4 p-5">
        <h2 class="section-title">Informasi kegiatan</h2>
        <p class="muted whitespace-pre-line break-words">{{ $booking->description }}</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-muted">Ruangan</dt><dd class="mt-1">{{ $booking->room?->name ?? 'Hanya peralatan' }}</dd></div>
            <div><dt class="text-xs text-muted">Kehadiran</dt><dd class="mt-1">{{ $booking->participants }} peserta · {{ $booking->committee }} panitia</dd></div>
            @if($admin)
                <div><dt class="text-xs text-muted">Pemohon</dt><dd class="mt-1 break-words">{{ $booking->profile?->full_name }} · {{ $booking->profile?->email }}</dd></div>
                <div><dt class="text-xs text-muted">Nomor pengajuan</dt><dd class="mt-1 break-all font-mono text-xs">{{ $booking->id }}</dd></div>
            @endif
        </dl>
        @if($booking->review_note)<x-notice>{{ $booking->review_note }}</x-notice>@endif
        @if($booking->required_facilities)
            <p class="muted">Fasilitas yang diperlukan · {{ implode(', ', array_map(fn ($facility) => __('campusspace.facilities.'.$facility), $booking->required_facilities)) }}</p>
        @endif
        @if($booking->planner)
            <div class="rounded-lg border border-line bg-[#0e141c] p-3">
                <h3 class="text-sm font-semibold">Denah terlampir</h3>
                <p class="muted mt-1">{{ $booking->planner['arrangement'] ?? 'Susunan tersimpan' }} · {{ $booking->planner['chairs'] ?? 0 }} kursi · {{ $booking->planner['tables'] ?? 0 }} meja</p>
            </div>
        @endif
    </section>
    <section class="panel grid content-start gap-4 p-5">
        <h2 class="section-title">Peralatan</h2>
        <ul class="divide-y divide-line">
            @forelse($booking->items as $item)
                @php($loan = $booking->loans->firstWhere('equipment_id', $item->equipment_id))
                <li class="grid gap-1 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><span>{{ $item->equipment?->name ?? 'Peralatan' }}</span><span class="chip">{{ $item->quantity }} unit</span></div>
                    @if($loan)
                        <p class="muted">{{ $loan->returned_at ? 'Dikembalikan' : 'Diambil' }} {{ ($loan->returned_at ?? $loan->picked_up_at)->timezone(config('campusspace.timezone'))->format('d M Y · H:i') }} WIB</p>
                        @if($loan->returned_at && ($loan->missing || $loan->damaged))<p class="text-xs text-warning">{{ $loan->missing }} hilang · {{ $loan->damaged }} rusak</p>@endif
                        @if($loan->return_note)<p class="muted break-words">{{ $loan->return_note }}</p>@endif
                    @endif
                </li>
            @empty
                <li class="muted">Tidak ada peralatan yang dipinjam.</li>
            @endforelse
        </ul>
    </section>
</div>
@if($admin)
    @include('bookings.admin-controls')
@else
    @can('cancel', $booking)
        <form method="post" action="{{ route('bookings.cancel', $booking) }}" data-confirm="Batalkan pengajuan ini?">@csrf
            <button class="button button-danger"><x-icon name="x" class="size-4" /> Batalkan pengajuan</button>
        </form>
    @endcan
@endif
@endsection
