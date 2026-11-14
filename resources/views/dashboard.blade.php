@extends('layouts.shell')
@section('title', 'Beranda')

@section('content')
<section class="relative isolate grid min-w-0 overflow-hidden rounded-2xl border border-[#2e415d] bg-[#131e31] md:min-h-76">
    <img src="{{ asset('images/rooms/seminar.png') }}" alt="" fetchpriority="high" class="absolute inset-y-0 right-0 size-full object-cover object-center md:w-[68%] md:object-right">
    <div aria-hidden="true" class="absolute inset-0 bg-linear-to-r from-[#111d31] via-[#111d31]/95 to-[#111d31]/50 md:via-[#111d31]/88 md:to-[#111d31]/10"></div>
    <div class="relative grid max-w-145 content-center gap-4 px-5 py-9 sm:px-9 sm:py-12">
        <h1 class="text-[clamp(2rem,4.5vw,3.3rem)] leading-[1.08] font-semibold tracking-[-0.055em] text-white">Cari ruangan untuk kegiatanmu</h1>
        <p class="max-w-sm text-sm leading-relaxed text-[#d3dfef] sm:text-base">Temukan ruangan yang tersedia dan ajukan peminjaman dengan mudah.</p>
        <div class="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2">
            @if($user)
                <a href="#cari-ruangan" class="button button-primary">Cari Ruangan <x-icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('bookings.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-white hover:underline">Lihat Peminjaman Saya</a>
            @else
                <a href="{{ route('login') }}" class="button button-primary">{{ config('campusspace.demo') ? 'Mulai demo' : 'Masuk' }} <x-icon name="arrow-right" class="size-4" /></a>
                @unless(config('campusspace.demo'))
                    <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-white hover:underline">Daftar akun</a>
                @endunless
            @endif
        </div>
    </div>
</section>
@if($user)
    <section id="cari-ruangan" aria-labelledby="search-title" class="panel grid min-w-0 scroll-mt-4 gap-4 p-4 sm:p-6">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="search-title" class="section-title">Pilih jadwal</h2>
            <span class="text-xs text-muted">Waktu WIB</span>
        </div>
        <x-room-search />
    </section>
    <section class="grid min-w-0 gap-3" aria-labelledby="dashboard-rooms" x-data="roomMonitoring" data-url="{{ route('monitoring.snapshot') }}">
        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
            <div>
                <h2 id="dashboard-rooms" class="section-title">Ruangan kampus</h2>
                <p class="muted mt-1" x-text="snapshot && !error ? snapshot.summary.available + ' ruangan tersedia sekarang' : 'Cek jadwal untuk memastikan ruangan tersedia.'">Cek jadwal untuk memastikan ruangan tersedia.</p>
            </div>
            <a href="{{ route('monitoring.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-accent hover:underline">Lihat status ruangan <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        @if($rooms->isNotEmpty())
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@foreach($rooms as $room)<x-room-card :room="$room" live />@endforeach</div>
        @else
            <p class="muted panel p-5">Belum ada ruangan di katalog.</p>
        @endif
    </section>
    @if($latestBooking)
        <section class="grid gap-3" aria-labelledby="latest-booking">
            <h2 id="latest-booking" class="section-title">Peminjaman terakhir</h2>
            <x-booking-list :bookings="[$latestBooking]" />
        </section>
    @endif
@endif
@endsection
