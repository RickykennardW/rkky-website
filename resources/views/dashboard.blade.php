@extends('layouts.shell')
@section('title', 'Beranda')

@section('content')
<section class="panel flex min-w-0 flex-col gap-5 border-brand/20 bg-linear-to-br from-brand/10 to-panel p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6" aria-labelledby="dashboard-welcome">
    <div class="grid min-w-0 gap-2">
        <h1 id="dashboard-welcome" class="page-title break-words">
            @if($user)Halo, {{ Str::of($user->profile->full_name)->trim()->before(' ') }}!@else Cari ruangan untuk kegiatanmu @endif
        </h1>
        <p class="muted">Cari ruangan dan peralatan kampus untuk kegiatanmu.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        @if($user)
            <a class="button button-primary" href="{{ route('rooms.index') }}">Cari Ruangan <x-icon name="arrow-right" class="size-4" /></a>
        @else
            <a class="button button-primary" href="{{ route('login') }}">{{ config('campusspace.demo') ? 'Mulai demo' : 'Masuk' }} <x-icon name="arrow-right" class="size-4" /></a>
            @unless(config('campusspace.demo'))
                <a class="button button-ghost" href="{{ route('register') }}">Daftar akun</a>
            @endunless
        @endif
    </div>
</section>
@if($user)
    <section class="panel flex min-w-0 flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" aria-labelledby="quick-booking">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand/10 text-accent">
                <x-icon name="calendar-plus" class="size-5" />
            </span>
            <div class="grid gap-1">
                <h2 id="quick-booking" class="section-title">Peminjaman cepat</h2>
                <p class="muted">Sudah tahu yang ingin dipinjam? Langsung isi pengajuan.</p>
            </div>
        </div>
        <a class="button button-secondary self-start sm:self-auto" href="{{ route('bookings.create') }}">Ajukan Peminjaman <x-icon name="arrow-right" class="size-4" /></a>
    </section>
    <section class="grid min-w-0 gap-3" aria-labelledby="dashboard-bookings">
        <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-x-5">
            <h2 id="dashboard-bookings" class="section-title">Peminjaman Saya</h2>
            <a class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-accent hover:underline" href="{{ route('bookings.index') }}">Lihat Semua Peminjaman <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        @if($bookings->isEmpty())
            <div class="panel flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                <p class="muted">Kamu belum punya peminjaman.</p>
                <a class="button button-ghost" href="{{ route('rooms.index') }}">Cari Ruangan</a>
            </div>
        @else
            <ul class="panel divide-y divide-line">
                @foreach($bookings as $booking)
                    <li>
                        <a class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 rounded-lg p-4 hover:bg-raised sm:flex sm:gap-4 sm:px-5" href="{{ route('bookings.show', $booking) }}">
                            <span class="col-span-2 grid min-w-0 flex-1 gap-1">
                                <span class="break-words text-sm font-semibold">{{ $booking->room?->name ?? 'Peminjaman peralatan' }}</span>
                                <span class="text-xs leading-relaxed text-muted">{{ $booking->period_label }}</span>
                            </span>
                            <x-status :status="$booking->status" class="w-fit" />
                            <span class="inline-flex min-h-8 items-center gap-1.5 text-sm font-medium text-accent">Lihat detail <x-icon name="chevron-right" class="size-4" /></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
@endsection
