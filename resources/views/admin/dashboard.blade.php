@extends('layouts.shell')
@section('title', 'Beranda Admin')

@section('content')
<x-heading title="Beranda Admin" />
<section class="grid min-w-0 gap-3" aria-labelledby="pending-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="pending-title" class="section-title">Menunggu persetujuan <span class="chip ml-1">{{ $pendingCount }}</span></h2>
        <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="text-sm font-semibold text-accent hover:underline">Lihat semua permintaan</a>
    </div>
    <x-booking-list :bookings="$pending" admin empty="Tidak ada permintaan baru" />
</section>
<div class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <section class="grid content-start gap-3" aria-labelledby="today-title">
        <h2 id="today-title" class="section-title">Peminjaman hari ini</h2>
        <x-booking-list :bookings="$todayBookings" admin empty="Tidak ada peminjaman hari ini" />
    </section>
    <div class="grid content-start gap-4">
        <section class="panel grid gap-2 p-4 sm:p-5" x-data="roomMonitoring" data-url="{{ route('monitoring.snapshot') }}">
            <h2 class="section-title">Ruangan sedang digunakan</h2>
            <p class="muted" x-text="snapshot && !error ? snapshot.summary.in_use + ' ruangan sedang digunakan' : 'Lihat status berdasarkan jadwal.'">Lihat status berdasarkan jadwal.</p>
            <a class="mt-1 w-fit text-sm font-semibold text-accent hover:underline" href="{{ route('monitoring.index') }}">Pantau ruangan</a>
        </section>
        <section class="panel grid gap-2 p-4 sm:p-5">
            <h2 class="section-title">Peralatan</h2>
            <p class="muted">{{ $overdue }} terlambat dikembalikan · {{ $activeLoans }} belum kembali</p>
            @if($losses)<p class="text-sm text-warning">{{ $losses }} catatan pengembalian memerlukan pemeriksaan stok.</p>@endif
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold text-accent">
                @if(config('campusspace.demo'))
                    <a class="hover:underline" href="{{ route('equipment.index') }}">Lihat peralatan</a>
                @else
                    <a class="hover:underline" href="{{ route('admin.loans.index', ['status' => $overdue ? 'overdue' : 'active']) }}">Cek pengembalian</a>
                    <a class="hover:underline" href="{{ route('admin.equipment.index') }}">Kelola peralatan</a>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
