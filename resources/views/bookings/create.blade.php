@extends('layouts.shell')
@section('title', 'New booking')

@section('content')
<x-heading title="New booking" description="Request a room, equipment, or both. A campus administrator reviews each request." />
@php
    $roomId = old('room_id', $selectedRoom?->id);
    $attachedPlan = old('planner', $plan ? json_encode($plan, JSON_THROW_ON_ERROR) : '');
@endphp
<form method="post" action="{{ route('bookings.store') }}" class="grid gap-5" x-data="bookingForm"
      x-on:submit="review($event)" x-on:change="changed($event)" data-availability-url="{{ route('availability') }}" data-planner-url="{{ route('planner.show') }}">
    @csrf
    <input type="hidden" name="planner" value="{{ $attachedPlan }}" x-ref="plan">
    <section class="panel grid gap-4 p-4 sm:p-6">
        <div><h2 class="section-title">Room and schedule</h2><p class="muted mt-1">Times are in Asia/Jakarta (WIB).</p></div>
        <label class="label">Room<select class="input" name="room_id">
            <option value="">Equipment only</option>
            @foreach($rooms as $room)<option value="{{ $room->id }}" @selected($roomId === $room->id)>{{ $room->name }} · {{ $room->capacity }} people</option>@endforeach
        </select></label>
        <x-period-fields :period="$period" required />
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" x-on:click="check()" x-bind:disabled="checking" class="button button-secondary"><x-icon name="calendar-range" class="size-4" /><span x-text="checking ? 'Checking…' : 'Check availability'">Check availability</span></button>
            <span role="status" class="text-sm" x-bind:class="availabilityError ? 'text-negative' : 'text-positive'" x-text="availabilityMessage"></span>
        </div>
    </section>
    <section class="panel grid gap-4 p-4 sm:p-6">
        <h2 class="section-title">Event details</h2>
        <label class="label">Event name<input class="input" name="title" required minlength="2" maxlength="120" value="{{ old('title') }}" placeholder="e.g. Student committee workshop"></label>
        <label class="label">Purpose or description<textarea class="input min-h-24 resize-y" name="description" required minlength="2" maxlength="2000">{{ old('description') }}</textarea></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="label">Participants<input class="input" name="participants" type="number" min="1" max="500" required value="{{ old('participants', $plan['participants'] ?? 1) }}"></label>
            <label class="label">Committee members<input class="input" name="committee" type="number" min="0" max="500" required value="{{ old('committee', $plan['committee'] ?? 0) }}"></label>
        </div>
        <div class="flex flex-wrap items-center gap-3" x-show="roomId" x-cloak>
            <a :href="plannerUrl()" class="button button-secondary"><x-icon name="grid-2x2" class="size-4" /> Open room planner</a>
            <span x-show="hasPlan" class="text-sm text-positive">Room plan attached. <button type="button" x-on:click="removePlan()" class="ml-1 underline">Remove plan</button></span>
        </div>
    </section>
    <section class="panel grid gap-4 p-4 sm:p-6">
        <div><h2 class="section-title">Equipment</h2><p class="muted mt-1">Set a quantity for each item. Stock is checked again when you submit.</p></div>
        <ul class="divide-y divide-line">
            @forelse($equipment as $item)
                @php($maximum = $item->usableQuantity())
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0"><p class="break-words text-sm font-medium">{{ $item->name }}</p><p class="mt-1 text-xs text-muted">{{ $item->category }} · <span x-text="quantityLabel('{{ $item->id }}', {{ $maximum }})">{{ $maximum }} usable units</span></p></div>
                    <label class="w-22 shrink-0"><span class="sr-only">{{ $item->name }} quantity</span>
                        <input class="input" type="number" min="0" max="{{ $maximum }}" x-bind:max="limits?.['{{ $item->id }}'] ?? {{ $maximum }}"
                               name="quantities[{{ $item->id }}]" data-equipment-name="{{ $item->name }}"
                               value="{{ old('quantities.'.$item->id, $selectedEquipment === $item->id && $maximum > 0 ? 1 : 0) }}">
                    </label>
                </li>
            @empty
                <li class="muted py-2">No equipment has been added to the catalog.</li>
            @endforelse
        </ul>
    </section>
    <button type="submit" class="button button-primary justify-self-start">Review request <x-icon name="arrow-right" class="size-4" /></button>
    <dialog x-ref="reviewDialog" aria-labelledby="review-title" class="m-auto w-[min(92vw,32rem)] max-h-[90dvh] overflow-auto rounded-xl border border-line bg-panel p-6 text-ink backdrop:bg-black/70">
        <div class="grid gap-4">
            <h2 id="review-title" class="section-title">Review your request</h2>
            <p class="muted whitespace-pre-line" x-text="summary"></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="submitConfirmed()" class="button button-primary"><x-icon name="calendar-check" class="size-4" /> Submit request</button>
                <button type="button" x-on:click="$refs.reviewDialog.close()" class="button button-secondary">Edit details</button>
            </div>
        </div>
    </dialog>
</form>
@endsection
