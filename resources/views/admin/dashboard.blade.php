@extends('layouts.shell')
@section('title', 'Admin overview')

@section('content')
<x-heading title="Admin overview" description="Review requests, track room usage, and manage campus inventory.">
    <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="button button-primary">Review requests <x-icon name="arrow-right" class="size-4" /></a>
</x-heading>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat icon="clock-3" label="Pending requests" :count="$pendingCount" detail="Awaiting a decision" />
    <x-stat icon="calendar-check" label="Approved reservations" :count="$approvedCount" detail="Current approved requests" />
    <x-stat icon="package-check" label="Active equipment loans" :count="$activeLoans" detail="Item lines awaiting return" />
    <x-stat icon="building-2" label="Rooms in service" :count="$activeRooms" :detail="$roomCount.' rooms in the catalog'" />
</div>
@if($losses > 0)
    <x-notice>{{ $losses }} return {{ Str::plural('record', $losses) }} flagged a stock loss affecting approved allocations. <a href="{{ route('admin.equipment.index') }}" class="underline">Review inventory</a>.</x-notice>
@endif
<section class="panel flex flex-wrap items-center justify-between gap-3 p-5">
    <div><h2 class="section-title">Inventory summary</h2><p class="muted mt-1">{{ $equipmentCount }} equipment types · {{ $usable }} usable units across available items.</p></div>
    <a href="{{ route('admin.equipment.index') }}" class="button button-secondary">Manage inventory <x-icon name="arrow-right" class="size-4" /></a>
</section>
<div class="grid gap-5 xl:grid-cols-2">
    <section class="grid content-start gap-3"><h2 class="section-title">Pending review</h2><x-booking-list :bookings="$pending" admin empty="No pending requests" /></section>
    <section class="grid content-start gap-3"><h2 class="section-title">Upcoming room usage</h2><x-booking-list :bookings="$upcoming" admin empty="No upcoming room usage" /></section>
</div>
@endsection
