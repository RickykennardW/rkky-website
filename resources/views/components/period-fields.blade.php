@props(['period' => [], 'required' => false, 'compact' => false])
@php
    $separateEnd = $compact && old('end_date', $period['end_date'] ?? null) !== old('date', $period['date'] ?? null);
    $fields = [['date', 'Tanggal mulai', 'date'], ['start', 'Jam mulai', 'time']];
    if (!$compact) $fields[] = ['end_date', 'Tanggal selesai', 'date'];
    $fields[] = ['end', 'Jam selesai', 'time'];
@endphp
<div class="grid grid-cols-2 gap-3 {{ $compact ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }}">
    @foreach($fields as [$name, $label, $type])
        <label class="label {{ $compact && $name === 'date' ? 'col-span-2 sm:col-span-1' : '' }}">{{ $label }}
            <input class="input @error($name) border-negative @enderror" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $period[$name] ?? '') }}" @required($required) @error($name) aria-invalid="true" @enderror>
            @error($name)<span class="text-xs text-negative">{{ $message }}</span>@enderror
        </label>
    @endforeach
</div>
@if($compact)
    <details @if($separateEnd) open @endif>
        <summary class="w-fit py-1.5 text-sm font-medium text-accent">Berakhir di tanggal lain?</summary>
        <label class="label mt-2 max-w-60">Tanggal selesai
            <input class="input" name="end_date" type="date" value="{{ $separateEnd ? old('end_date', $period['end_date'] ?? null) : '' }}">
            @error('end_date')<span class="text-xs text-negative">{{ $message }}</span>@enderror
        </label>
    </details>
@endif
