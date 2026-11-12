@extends('layouts.shell')
@section('title', $room ? 'Edit room' : 'Add room')

@section('content')
<x-breadcrumb :href="route('admin.rooms.index')" label="Manage rooms" :current="$room ? 'Edit room' : 'Add room'" />
<x-heading :title="$room ? 'Edit '.$room->name : 'Add room'" description="Set the room information, usable dimensions and facilities." />
<form method="post" action="{{ route('admin.rooms.save', $room ? [$room] : []) }}" class="grid gap-5">
    @csrf
    <section class="panel grid gap-4 p-5 sm:p-6">
        <h2 class="section-title">Room information</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="label">Name<input class="input" name="name" required minlength="2" maxlength="100" value="{{ old('name', $room?->name) }}"></label>
            <label class="label">Location<input class="input" name="location" required minlength="2" maxlength="120" value="{{ old('location', $room?->location) }}"></label>
        </div>
        <label class="label">Description<textarea class="input min-h-20" name="description" required maxlength="1500">{{ old('description', $room?->description) }}</textarea></label>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach(['capacity' => ['Capacity', 1], 'chairs' => ['Chairs', 0], 'tables_count' => ['Tables', 0]] as $name => [$label, $minimum])
                <label class="label">{{ $label }}<input class="input" name="{{ $name }}" type="number" min="{{ $minimum }}" max="500" required value="{{ old($name, $room?->{$name}) }}"></label>
            @endforeach
        </div>
        <label class="label max-w-xs">Illustration
            <select name="photo" class="input"><option value="">No illustration</option>
                @foreach(['seminar', 'classroom', 'hall'] as $option)<option value="{{ $option }}" @selected(old('photo', $room?->photo) === $option)>{{ ucfirst($option) }}</option>@endforeach
            </select>
        </label>
        <input type="hidden" name="active" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="checkbox" @checked(old('active', $room?->active ?? true))> Available for reservations</label>
        <fieldset class="grid gap-3"><legend class="mb-2 text-sm font-medium">Facilities</legend>
            <div class="flex flex-wrap gap-2">
                @foreach($facilities as $facility)
                    <label class="chip min-h-9 cursor-pointer">
                        <input class="checkbox" type="checkbox" name="facilities[]" value="{{ $facility }}" @checked(in_array($facility, old('facilities', $room?->facilities->pluck('facility')->all() ?? []), true))>{{ $facility }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    </section>
    <section class="panel grid gap-4 p-5 sm:p-6">
        <div><h2 class="section-title">Room geometry</h2><p class="muted mt-1">Enter measured interior dimensions and door clearance in metres. The planner uses these measurements to estimate fit.</p></div>
        <div class="grid grid-cols-2 gap-4">
            @foreach(['width' => 'Width', 'depth' => 'Depth'] as $name => $label)
                <label class="label">{{ $label }} (m)<input type="number" name="layout[{{ $name }}]" class="input" min="3" max="60" step="0.01" required value="{{ old('layout.'.$name, $layout[$name] ?? null) }}"></label>
            @endforeach
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="label">Door wall
                <select name="layout[door][wall]" class="input" required>
                    <option value="">Select a wall</option>
                    @foreach(['north', 'south', 'east', 'west'] as $wall)<option value="{{ $wall }}" @selected(old('layout.door.wall', data_get($layout, 'door.wall')) === $wall)>{{ ucfirst($wall) }}</option>@endforeach
                </select>
            </label>
            @foreach(['offset' => ['Door offset', '0', '60'], 'width' => ['Door width', '0.8', '5'], 'clearance' => ['Door clearance', '0.9', '5']] as $name => [$label, $min, $max])
                <label class="label">{{ $label }} (m)<input name="layout[door][{{ $name }}]" class="input" type="number" min="{{ $min }}" max="{{ $max }}" step="0.01" required value="{{ old('layout.door.'.$name, data_get($layout, 'door.'.$name)) }}"></label>
            @endforeach
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach(['blocked' => 'Blocked zones', 'fixed' => 'Fixed equipment'] as $name => $label)
                @php($value = old('layout.'.$name, $layout[$name] ?? []))
                <label class="label">{{ $label }} (JSON)
                    <textarea class="input min-h-24 resize-y font-mono text-xs" name="layout[{{ $name }}]" required>{{ is_string($value) ? $value : json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
                    <span class="text-xs text-muted">Each entry uses x, y, width, depth and label. Use [] when there are no entries.</span>
                </label>
            @endforeach
        </div>
    </section>
    <div class="flex flex-wrap gap-2"><button class="button button-primary"><x-icon name="circle-check" class="size-4" /> Save room</button><a class="button button-ghost" href="{{ route('admin.rooms.index') }}">Cancel</a></div>
</form>
@endsection
