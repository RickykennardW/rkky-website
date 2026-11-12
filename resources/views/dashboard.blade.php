@extends('layouts.shell')
@section('title', 'Dashboard')

@section('content')
<x-heading title="Dashboard" :description="$user ? 'Welcome back, '.str($user->profile->full_name)->before(' ').'. Manage your campus reservations here.' : 'Campus rooms, equipment, and reservations in one place.'" />
<section class="relative isolate flex min-h-[254px] items-center overflow-hidden rounded-xl border border-[#31445d] bg-[#13243a] sm:min-h-[294px]">
    <img src="{{ asset('images/rooms/seminar.png') }}" alt="" fetchpriority="high" class="absolute inset-0 size-full object-cover object-[center_57%]">
    <div aria-hidden="true" class="absolute inset-0 bg-linear-to-r from-[#0a172a] via-[#0b192a]/90 to-[#0b1726]/25"></div>
    <div class="relative grid max-w-[530px] gap-4 px-6 py-8 sm:px-9 sm:py-10">
        <h2 class="text-[clamp(1.9rem,4vw,3.2rem)] leading-[1.07] font-semibold tracking-[-0.055em] text-white">Book a campus room</h2>
        <p class="max-w-md text-sm leading-relaxed text-[#c9d7e8] sm:text-base">Find a room for your next event, then add the equipment you need.</p>
        <div class="mt-2 flex flex-wrap gap-2.5">
            @if($user)
                <a href="{{ route('rooms.index') }}" class="button button-primary">Explore rooms <x-icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('equipment.index') }}" class="button button-secondary border-[#46607d] bg-[#152840]"><x-icon name="boxes" class="size-4" /> Equipment</a>
            @else
                <a href="{{ route('login') }}" class="button button-primary">{{ config('campusspace.demo') ? 'Start demo' : 'Sign in' }} <x-icon name="arrow-right" class="size-4" /></a>
                @unless(config('campusspace.demo'))
                    <a href="{{ route('register') }}" class="button button-secondary border-[#46607d] bg-[#152840]">Create an account</a>
                @endunless
            @endif
        </div>
    </div>
</section>
@if($user)
    <section aria-label="Reservation summary" class="grid gap-3 sm:grid-cols-3 sm:gap-4">
        <x-stat icon="calendar-days" label="Upcoming reservations" :count="$upcomingCount" detail="Approved and still ahead" />
        <x-stat icon="clock-3" label="Pending requests" :count="$pendingCount" detail="Waiting for campus review" />
        <x-stat icon="calendar-check" label="Approved bookings" :count="$approvedCount" detail="Confirmed by an administrator" />
    </section>
    <section class="grid gap-4" aria-labelledby="dashboard-rooms">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div><h2 id="dashboard-rooms" class="section-title">Campus rooms</h2><p class="muted mt-1">Compare room sizes and facilities.</p></div>
            <a href="{{ route('rooms.index') }}" class="flex min-h-9 items-center gap-2 text-sm font-semibold text-accent hover:text-ink">View all rooms <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        @if($rooms->isNotEmpty())
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@foreach($rooms as $room)<x-room-card :room="$room" />@endforeach</div>
        @else
            <p class="muted panel p-5">The campus catalog has no rooms yet.</p>
        @endif
    </section>
    <section class="grid gap-4"><h2 class="section-title">Upcoming reservations</h2><x-booking-list :bookings="$upcoming" empty="No upcoming reservations" /></section>
@endif
@endsection
