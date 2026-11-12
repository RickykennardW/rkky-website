@extends('layouts.shell')
@section('title', $admin ? 'Review booking' : 'Booking details')

@section('content')
<x-breadcrumb :href="route($admin ? 'admin.bookings.index' : 'bookings.index')" :label="$admin ? 'Booking requests' : 'My bookings'" current="Details" />
<x-heading :title="$booking->title" :description="$booking->period_label"><x-status :status="$booking->status" /></x-heading>
<div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
    <section class="panel grid content-start gap-4 p-5">
        <h2 class="section-title">Reservation details</h2>
        <p class="muted whitespace-pre-line break-words">{{ $booking->description }}</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-muted">Room</dt><dd class="mt-1">{{ $booking->room?->name ?? 'Equipment only' }}</dd></div>
            <div><dt class="text-xs text-muted">Attendance</dt><dd class="mt-1">{{ $booking->participants }} participants · {{ $booking->committee }} committee</dd></div>
            <div><dt class="text-xs text-muted">Requested by</dt><dd class="mt-1 break-words">{{ $booking->profile?->full_name }}@if($admin) · {{ $booking->profile?->email }}@endif</dd></div>
            <div><dt class="text-xs text-muted">Booking ID</dt><dd class="mt-1 break-all font-mono text-xs">{{ $booking->id }}</dd></div>
        </dl>
        @if($booking->review_note)<x-notice>{{ $booking->review_note }}</x-notice>@endif
        @if($booking->required_facilities)
            <p class="muted">Required facilities · {{ implode(', ', $booking->required_facilities) }}</p>
        @endif
        @if($booking->planner)
            <div class="rounded-lg border border-line bg-[#0e141c] p-3">
                <h3 class="text-sm font-semibold">Attached room plan</h3>
                <p class="muted mt-1">{{ $booking->planner['arrangement'] ?? 'Saved arrangement' }} · {{ $booking->planner['chairs'] ?? 0 }} chairs · {{ $booking->planner['tables'] ?? 0 }} tables</p>
            </div>
        @endif
    </section>
    <section class="panel grid content-start gap-4 p-5">
        <h2 class="section-title">Equipment</h2>
        <ul class="divide-y divide-line">
            @forelse($booking->items as $item)
                @php($loan = $booking->loans->firstWhere('equipment_id', $item->equipment_id))
                <li class="grid gap-1 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><span>{{ $item->equipment?->name ?? 'Equipment' }}</span><span class="chip">{{ $item->quantity }} units</span></div>
                    @if($loan)
                        <p class="muted">{{ $loan->returned_at ? 'Returned' : 'Picked up' }} {{ ($loan->returned_at ?? $loan->picked_up_at)->timezone(config('campusspace.timezone'))->format('d M Y · H:i') }} WIB</p>
                        @if($loan->returned_at && ($loan->missing || $loan->damaged))<p class="text-xs text-warning">{{ $loan->missing }} missing · {{ $loan->damaged }} damaged</p>@endif
                        @if($loan->return_note)<p class="muted break-words">{{ $loan->return_note }}</p>@endif
                    @endif
                </li>
            @empty
                <li class="muted">No equipment was requested.</li>
            @endforelse
        </ul>
    </section>
</div>
@if($admin)
    @include('bookings.admin-controls')
@else
    @can('cancel', $booking)
        <form method="post" action="{{ route('bookings.cancel', $booking) }}" data-confirm="Cancel this pending request?">@csrf
            <button class="button button-danger"><x-icon name="x" class="size-4" /> Cancel request</button>
        </form>
    @endcan
@endif
@endsection
