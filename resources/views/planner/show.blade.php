@extends('layouts.shell')
@section('title', 'Room planner')

@section('content')
<x-heading title="Smart Room Planner" description="Estimate furniture and preview a setup using measured room dimensions." />
@if(!$room)
    <x-empty icon="grid-2x2" title="No active rooms to plan" description="An administrator needs to add a room with its actual dimensions." :href="route('rooms.index')" action="Browse rooms" />
@else
    <div class="grid gap-5" x-data="roomPlanner"
         data-initial="{{ json_encode($result, JSON_THROW_ON_ERROR) }}" data-preview-url="{{ route('planner.preview') }}" data-book-url="{{ route('bookings.create') }}">
        <form x-ref="planForm" x-on:submit.prevent="calculate()" x-on:change="calculate()" class="grid items-start gap-5 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <section class="panel grid gap-5 p-5">
                <h2 class="section-title flex items-center gap-2"><x-icon name="armchair" class="text-accent" /> Event setup</h2>
                <label class="label">Room<select name="room_id" class="input">
                    @foreach($rooms as $option)<option value="{{ $option->id }}" @selected($room->id === $option->id)>{{ $option->name }}</option>@endforeach
                </select></label>
                <label class="label">Event type<select name="eventType" class="input">
                    @foreach($events as $event)<option @selected($result['plan']['eventType'] === $event)>{{ $event }}</option>@endforeach
                </select></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Participants<input class="input" name="participants" type="number" required min="1" max="500" value="{{ $result['plan']['participants'] }}"></label>
                    <label class="label">Committee<input class="input" name="committee" type="number" required min="0" max="500" value="{{ $result['plan']['committee'] }}"></label>
                </div>
                <fieldset class="grid gap-3">
                    <legend class="sr-only">Event requirements</legend>
                    @foreach(['presentation' => 'Presentation required', 'registrationDesk' => 'Registration desk required', 'discussion' => 'Group discussion required'] as $name => $label)
                        <label class="flex items-center gap-2.5 text-sm"><input class="checkbox" type="checkbox" name="{{ $name }}" value="1" @checked($result['plan'][$name])>{{ $label }}</label>
                    @endforeach
                </fieldset>
                <div class="grid gap-3 border-t border-line pt-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold">Suggested equipment</h3><button type="button" x-on:click="resetQuantities()" class="text-xs font-semibold text-accent hover:underline">Reset estimate</button></div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['chairs' => 'Chairs', 'tables' => 'Tables', 'microphones' => 'Microphones', 'projectors' => 'Projectors', 'cables' => 'Extension cables'] as $key => $label)
                            <label class="label">{{ $label }}
                                <input class="input" name="{{ $key }}" data-override type="number" min="0" max="500" placeholder="{{ $result['plan'][$key] }}" x-bind:placeholder="result?.plan?.['{{ $key }}'] ?? ''">
                            </label>
                        @endforeach
                    </div>
                    <p class="muted">Estimates seat all participants and half of the committee, rounded up. Group tables seat four. A registration desk uses one table.</p>
                </div>
            </section>
            <section class="panel grid min-w-0 content-start gap-4 p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 class="section-title">Layout preview</h2><p class="muted mt-1" x-text="dimensions()"></p></div>
                    <label class="label">Arrangement<select name="arrangement" class="input">
                        <option value="">Suggested arrangement</option>
                        @foreach($arrangements as $arrangement)<option>{{ $arrangement }}</option>@endforeach
                    </select></label>
                </div>
                <p x-show="error || result?.error" x-cloak class="rounded-lg border border-[#623c46] bg-[#301d24] p-3 text-sm text-negative" role="alert" x-text="error || result?.error"></p>
                <div class="grid min-h-48 place-items-center overflow-hidden rounded-lg border border-line bg-[#0e141c] p-3 sm:p-5">
                    <svg x-ref="drawing" class="max-h-[460px] w-full" role="img" aria-label="Two-dimensional room arrangement"></svg>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs text-muted">
                    @foreach(['#719af2' => 'Chairs', '#4ca69e' => 'Tables', '#9b7ccf' => 'Fixed equipment', '#bb6671' => 'Blocked zones', '#50667d' => 'Clearance', '#79cbc3' => 'Door'] as $color => $label)
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm" style="background:{{ $color }}"></span>{{ $label }}</span>
                    @endforeach
                </div>
                <p class="muted">Confirm the actual setup and access requirements with campus staff before the event.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="button button-secondary" x-bind:disabled="loading" x-text="loading ? 'Calculating…' : 'Recalculate'">Recalculate</button>
                    <button type="button" class="button button-primary" x-bind:disabled="loading || error || result?.error" x-on:click="usePlan()">Use plan in booking <x-icon name="arrow-right" class="size-4" /></button>
                </div>
            </section>
        </form>
        <noscript><x-notice>Enable JavaScript to calculate and attach a room plan.</x-notice></noscript>
    </div>
@endif
@endsection
