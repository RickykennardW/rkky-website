@extends('layouts.shell')
@section('title', 'Cari Jadwal Lain')

@section('content')
<x-heading title="Cari Jadwal Lain" description="Temukan ruangan atau jam lain yang sesuai dengan kebutuhanmu." />
<form method="post" action="{{ route('scheduling.check') }}" class="grid gap-5">
    @csrf
    <details class="panel p-5" @if($interpreted || old('request_text')) open @endif>
        <summary class="font-semibold">Isi jadwal dari deskripsi</summary>
        <div class="mt-3 grid gap-3">
        <label class="label">Tulis kebutuhanmu
            <textarea class="input min-h-20" name="request_text" maxlength="500" placeholder="40 orang besok pukul 13:00 sampai 15:00 dengan proyektor">{{ old('request_text', $requestText) }}</textarea>
        </label>
        <p class="muted">Sebutkan tanggal, jam, jumlah orang, dan peralatan. Periksa kembali isian yang dihasilkan.</p>
        <button class="button button-secondary justify-self-start" name="action" value="interpret" formnovalidate>Isi dari deskripsi</button>
        </div>
    </details>
    <section class="panel grid gap-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Jadwal dan kebutuhan</h2>
            @if($exampleAvailable)
                <a class="button button-secondary" href="{{ route('scheduling.index', ['example' => 1]) }}">Coba contoh bentrok</a>
            @endif
        </div>
        @if($interpreted)<x-notice>Periksa jadwal dan lengkapi kebutuhanmu sebelum mencari.</x-notice>@endif
        <x-period-fields :period="$fields" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="label">Jumlah peserta<input name="participants" class="input" type="number" min="1" max="500" required value="{{ old('participants', $fields['participants']) }}"></label>
            <label class="label">Jumlah panitia<input name="committee" class="input" type="number" min="0" max="500" required value="{{ old('committee', $fields['committee']) }}"></label>
            <label class="label">Ruangan pilihan
                <select class="input" name="room">
                    <option value="">Ruangan mana saja</option>
                    @foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room', $fields['room'] ?? null) === $room->id)>{{ $room->name }}</option>@endforeach
                </select>
            </label>
            <label class="label">Susunan kursi
                <select class="input" name="arrangement">
                    <option value="">Susunan apa saja</option>
                    @foreach(\App\Services\SmartRoomPlanner::ARRANGEMENTS as $layout)
                        <option @selected(old('arrangement', $fields['arrangement'] ?? null) === $layout)>{{ $layout }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <details class="grid gap-3" @if(!empty($fields['required_facilities']) || array_filter($fields['quantities'] ?? [])) open @endif>
            <summary class="text-sm font-semibold">Fasilitas dan peralatan</summary>
            <div class="mt-3 grid gap-4">
                <x-required-facilities :selected="$fields['required_facilities'] ?? []" />
                <fieldset class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <legend class="mb-3 text-sm font-semibold">Peralatan yang diperlukan</legend>
            @foreach($equipment as $item)
                <label class="label">{{ $item->name }}
                    <input name="quantities[{{ $item->id }}]" type="number" class="input" min="0" max="10000"
                           value="{{ old('quantities.'.$item->id, $fields['quantities'][$item->id] ?? 0) }}">
                </label>
            @endforeach
                </fieldset>
            </div>
        </details>
        <button class="button button-primary justify-self-start" name="action" value="check"><x-icon name="calendar-check" class="size-4" /> Cari pilihan</button>
    </section>
</form>
@if($result)
    <details id="pilihan-jadwal" class="panel scroll-mt-4 p-5" @if(request()->filled('room')) open @endif>
        <summary class="font-semibold">Periksa hasil jadwal yang diminta</summary>
        <div class="mt-4 grid gap-4">
        <h2 id="schedule-results" class="section-title">Jadwal yang diminta</h2>
        <ul class="grid gap-3">
            @forelse($result['assessments'] as $room)
                <li class="grid gap-1 border-b border-line pb-3 last:border-0 last:pb-0">
                    <span class="text-sm font-semibold">{{ $room['name'] }}</span>
                    <span class="text-sm {{ $room['issues'] ? 'text-warning' : 'text-positive' }}">{{ $room['issues'] ? implode(' ', $room['issues']) : 'Tersedia dan sesuai kebutuhan.' }}</span>
                </li>
            @empty
                <li class="muted">Belum ada ruangan di katalog.</li>
            @endforelse
        </ul>
        </div>
    </details>
    @foreach(['sameTime' => 'Ruangan lain pada jam yang sama', 'preferredTimes' => 'Jam lain untuk ruangan pilihan', 'otherTimes' => 'Pilihan ruangan dan jam lain'] as $group => $title)
        @if($result[$group]->isNotEmpty())
            <section class="grid gap-3">
                <h2 class="section-title">{{ $title }}</h2>
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach($result[$group] as $option)
                        <article class="panel grid gap-3 p-4">
                            <h3 class="font-semibold">{{ $option['room']->name }}</h3>
                            <p class="text-sm text-accent">{{ $option['period']->startsAt->format('d M · H:i') }}–{{ $option['period']->endsAt->format($option['period']->startsAt->isSameDay($option['period']->endsAt) ? 'H:i' : 'd M · H:i') }} WIB</p>
                            <p class="muted">{{ implode(' ', $option['reasons']) }} @if($option['offset']) Mulai {{ abs($option['offset']) }} menit lebih {{ $option['offset'] < 0 ? 'awal' : 'lambat' }}. @endif</p>
                            <a class="button button-secondary justify-self-start" href="{{ route('bookings.create', $option['booking']) }}">Pilih jadwal <x-icon name="arrow-right" class="size-4" /></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
    @if($result['sameTime']->isEmpty() && $result['preferredTimes']->isEmpty() && $result['otherTimes']->isEmpty())
        <x-notice>Belum ada pilihan yang sesuai. Coba ubah jam, jumlah peserta, atau fasilitas.</x-notice>
    @endif
@endif
@endsection
