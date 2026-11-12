@props(['period' => [], 'required' => false])
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach([['date', 'Start date', 'date'], ['start', 'Start time', 'time'], ['end_date', 'End date', 'date'], ['end', 'End time', 'time']] as [$name, $label, $type])
        <label class="label">{{ $label }}
            <input class="input" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $period[$name] ?? '') }}" @required($required)>
        </label>
    @endforeach
</div>
