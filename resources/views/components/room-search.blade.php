@props(['filter' => [], 'facilities' => []])
@php
    $values = array_replace(\App\Services\BookingPeriod::defaults(), array_filter($filter));
    $endDate = $filter['end_date'] ?? null;
@endphp
<form method="get" action="{{ route('rooms.index') }}" class="grid min-w-0 gap-4" aria-label="Cari ruangan berdasarkan jadwal">
    <div class="grid min-w-0 grid-cols-2 items-end gap-3 sm:grid-cols-4 xl:grid-cols-[1.2fr_1fr_1fr_0.85fr_auto]">
        <label class="label col-span-2 sm:col-span-1">Tanggal
            <input class="input" type="date" name="date" value="{{ old('date', $values['date']) }}" min="{{ now(config('campusspace.timezone'))->toDateString() }}" required>
        </label>
        <label class="label">Jam mulai
            <input class="input" type="time" name="start" value="{{ old('start', $values['start']) }}" required>
        </label>
        <label class="label">Jam selesai
            <input class="input" type="time" name="end" value="{{ old('end', $values['end']) }}" required>
        </label>
        <label class="label col-span-2 sm:col-span-1">{{ !empty($filter['committee']) ? 'Total kehadiran' : 'Jumlah peserta' }}
            <input class="input" type="number" name="capacity" min="1" max="1000" value="{{ old('capacity', $filter['capacity'] ?? 1) }}" required>
            @if(!empty($filter['committee']))
                <span class="text-xs text-muted">Termasuk {{ $filter['committee'] }} panitia</span>
            @endif
        </label>
        <button class="button button-primary col-span-2 sm:col-span-4 xl:col-span-1"><x-icon name="search" class="size-[18px]" /> Cari Ruangan</button>
    </div>
    @if(!empty($filter['committee']))<input type="hidden" name="committee" value="{{ $filter['committee'] }}">@endif
    @foreach(array_filter($filter['quantities'] ?? []) as $id => $quantity)
        <input type="hidden" name="quantities[{{ $id }}]" value="{{ $quantity }}">
    @endforeach
    @if($facilities)
        <details class="group text-sm" @if(old('facility', $filter['facility'] ?? []) || old('end_date', $endDate) && old('end_date', $endDate) !== old('date', $values['date'])) open @endif>
            <summary class="w-fit rounded py-1.5 font-medium text-accent">Filter tambahan</summary>
            <div class="mt-3 grid gap-4 rounded-lg border border-line bg-canvas p-4">
                <label class="label max-w-60">Tanggal selesai jika berbeda
                    <input name="end_date" type="date" class="input" value="{{ old('end_date', $endDate !== ($filter['date'] ?? null) ? $endDate : null) }}">
                </label>
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-sm font-medium">Fasilitas</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($facilities as $facility)
                            <label class="chip min-h-10 cursor-pointer"><input class="checkbox" type="checkbox" name="facility[]" value="{{ $facility }}" @checked(in_array($facility, (array) old('facility', $filter['facility'] ?? []), true))>{{ __('campusspace.facilities.'.$facility) }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <a href="{{ route('scheduling.index') }}" class="inline-flex min-h-11 w-fit items-center text-sm font-semibold text-accent hover:underline">Isi jadwal dari deskripsi</a>
            </div>
        </details>
    @endif
</form>
