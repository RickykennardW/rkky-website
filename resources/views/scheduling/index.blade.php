@extends('layouts.shell')
@section('title', 'Smart Scheduling')

@section('content')
<x-heading title="Smart Scheduling" description="Check approved bookings, room fit, and equipment stock for the time you need." />
<form method="post" action="{{ route('scheduling.check') }}" class="grid gap-5">
    @csrf
    <section class="panel grid gap-3 p-5">
        <label class="label">Describe your request
            <textarea class="input min-h-20" name="request_text" maxlength="500" placeholder="40 people tomorrow from 13:00 to 15:00 with a projector">{{ old('request_text', $requestText) }}</textarea>
        </label>
        <p class="muted">Rule-based interpretation supports a date, 24-hour times, attendance, and common equipment. Review the filled fields before checking availability.</p>
        <button class="button button-secondary justify-self-start" name="action" value="interpret" formnovalidate>Fill from text</button>
    </section>
    <section class="panel grid gap-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Schedule and room requirements</h2>
            @if($exampleAvailable)
                <a class="button button-secondary" href="{{ route('scheduling.index', ['example' => 1]) }}">Load conflict example</a>
            @endif
        </div>
        @if($interpreted)<x-notice>Review the interpreted details and add any missing requirements.</x-notice>@endif
        <x-period-fields :period="$fields" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="label">Participants<input name="participants" class="input" type="number" min="1" max="500" required value="{{ old('participants', $fields['participants']) }}"></label>
            <label class="label">Committee members<input name="committee" class="input" type="number" min="0" max="500" required value="{{ old('committee', $fields['committee']) }}"></label>
            <label class="label">Preferred room
                <select class="input" name="room">
                    <option value="">Any suitable room</option>
                    @foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room', $fields['room'] ?? null) === $room->id)>{{ $room->name }}</option>@endforeach
                </select>
            </label>
            <label class="label">Preferred seating layout
                <select class="input" name="arrangement">
                    <option value="">Any layout</option>
                    @foreach(\App\Services\SmartRoomPlanner::ARRANGEMENTS as $layout)
                        <option @selected(old('arrangement', $fields['arrangement'] ?? null) === $layout)>{{ $layout }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <x-required-facilities :selected="$fields['required_facilities'] ?? []" />
        <fieldset class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <legend class="mb-3 text-sm font-semibold">Equipment needed</legend>
            @foreach($equipment as $item)
                <label class="label">{{ $item->name }}
                    <input name="quantities[{{ $item->id }}]" type="number" class="input" min="0" max="10000"
                           value="{{ old('quantities.'.$item->id, $fields['quantities'][$item->id] ?? 0) }}">
                </label>
            @endforeach
        </fieldset>
        <button class="button button-primary justify-self-start" name="action" value="check"><x-icon name="calendar-check" class="size-4" /> Check schedule</button>
    </section>
</form>
@if($result)
    <section class="panel grid gap-4 p-5" aria-labelledby="schedule-results">
        <h2 id="schedule-results" class="section-title">Requested time</h2>
        <ul class="grid gap-3">
            @forelse($result['assessments'] as $room)
                <li class="grid gap-1 border-b border-line pb-3 last:border-0 last:pb-0">
                    <span class="text-sm font-semibold">{{ $room['name'] }}</span>
                    <span class="text-sm {{ $room['issues'] ? 'text-warning' : 'text-positive' }}">{{ $room['issues'] ? implode(' ', $room['issues']) : 'Available and fits the request.' }}</span>
                </li>
            @empty
                <li class="muted">There are no rooms in the campus catalog.</li>
            @endforelse
        </ul>
    </section>
    @foreach(['sameTime' => 'Rooms at the requested time', 'preferredTimes' => 'Other times for your preferred room', 'otherTimes' => 'Other room and time combinations'] as $group => $title)
        @if($result[$group]->isNotEmpty())
            <section class="grid gap-3">
                <h2 class="section-title">{{ $title }}</h2>
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach($result[$group] as $option)
                        <article class="panel grid gap-3 p-4">
                            <h3 class="font-semibold">{{ $option['room']->name }}</h3>
                            <p class="text-sm text-accent">{{ $option['period']->startsAt->format('d M · H:i') }}–{{ $option['period']->endsAt->format($option['period']->startsAt->isSameDay($option['period']->endsAt) ? 'H:i' : 'd M · H:i') }} WIB</p>
                            <p class="muted">{{ implode(' ', $option['reasons']) }} @if($option['offset']) Starts {{ abs($option['offset']) }} minutes {{ $option['offset'] < 0 ? 'earlier' : 'later' }}. @endif</p>
                            <a class="button button-secondary justify-self-start" href="{{ route('bookings.create', $option['booking']) }}">Use this option <x-icon name="arrow-right" class="size-4" /></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
    @if($result['sameTime']->isEmpty() && $result['preferredTimes']->isEmpty() && $result['otherTimes']->isEmpty())
        <x-notice>No suitable options were found at the requested time or in the nearby slots checked.</x-notice>
    @endif
@endif
@endsection
