@extends('layouts.shell')
@section('title', 'Atur Ruangan')

@section('content')
<x-heading title="Bantu Atur Ruangan" description="Lihat denah dan perkiraan kebutuhan untuk kegiatanmu." />
@if(!$room)
    <x-empty icon="grid-2x2" title="Belum ada ruangan yang bisa diatur" description="Denah tersedia setelah admin melengkapi ukuran ruangan." :href="route('rooms.index')" action="Cari Ruangan" />
@else
    <div class="grid gap-5" x-data="roomPlanner"
         data-initial="{{ json_encode($result, JSON_THROW_ON_ERROR) }}" data-preview-url="{{ route('planner.preview') }}" data-book-url="{{ route('bookings.create') }}">
        <form x-ref="planForm" x-on:submit.prevent="calculate()" x-on:change="calculate()" class="grid items-start gap-5 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <section class="panel grid gap-5 p-5">
                <h2 class="section-title flex items-center gap-2"><x-icon name="armchair" class="text-accent" /> Kebutuhan kegiatan</h2>
                <label class="label">Ruangan<select name="room_id" class="input">
                    @foreach($rooms as $option)<option value="{{ $option->id }}" @selected($room->id === $option->id)>{{ $option->name }}</option>@endforeach
                </select></label>
                <label class="label">Jenis kegiatan<select name="eventType" class="input">
                    @foreach($events as $event)<option @selected($result['plan']['eventType'] === $event)>{{ $event }}</option>@endforeach
                </select></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Peserta<input class="input" name="participants" type="number" required min="1" max="500" value="{{ $result['plan']['participants'] }}"></label>
                    <label class="label">Panitia<input class="input" name="committee" type="number" required min="0" max="500" value="{{ $result['plan']['committee'] }}"></label>
                </div>
                <fieldset class="grid gap-3">
                    <legend class="sr-only">Kebutuhan kegiatan</legend>
                    @foreach(['presentation' => 'Perlu presentasi', 'registrationDesk' => 'Perlu meja pendaftaran', 'discussion' => 'Perlu diskusi kelompok'] as $name => $label)
                        <label class="flex items-center gap-2.5 text-sm"><input class="checkbox" type="checkbox" name="{{ $name }}" value="1" @checked($result['plan'][$name])>{{ $label }}</label>
                    @endforeach
                </fieldset>
                <div class="grid gap-3 border-t border-line pt-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold">Perkiraan peralatan</h3><button type="button" x-on:click="resetQuantities()" class="text-xs font-semibold text-accent hover:underline">Atur ulang</button></div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['chairs' => 'Kursi', 'tables' => 'Meja', 'microphones' => 'Mikrofon', 'projectors' => 'Proyektor', 'cables' => 'Kabel ekstensi'] as $key => $label)
                            <label class="label">{{ $label }}
                                <input class="input" name="{{ $key }}" data-override type="number" min="0" max="500" placeholder="{{ $result['plan'][$key] }}" x-bind:placeholder="result?.plan?.['{{ $key }}'] ?? ''">
                            </label>
                        @endforeach
                    </div>
                    <p class="muted">Meja kelompok memuat empat orang. Meja pendaftaran dihitung terpisah.</p>
                </div>
            </section>
            <section class="panel grid min-w-0 content-start gap-4 p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 class="section-title">Pratinjau denah</h2><p class="muted mt-1" x-text="dimensions()"></p></div>
                    <label class="label">Susunan<select name="arrangement" class="input">
                        <option value="">Susunan yang disarankan</option>
                        @foreach($arrangements as $arrangement)<option>{{ $arrangement }}</option>@endforeach
                    </select></label>
                </div>
                <p x-show="error || result?.error" x-cloak class="rounded-lg border border-[#623c46] bg-[#301d24] p-3 text-sm text-negative" role="alert" x-text="error || result?.error"></p>
                <div class="grid min-h-48 place-items-center overflow-hidden rounded-lg border border-line bg-[#0e141c] p-3 sm:p-5">
                    <svg x-ref="drawing" class="max-h-[460px] w-full" role="img" aria-label="Denah susunan ruangan"></svg>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs text-muted">
                    @foreach(['#719af2' => 'Kursi', '#4ca69e' => 'Meja', '#9b7ccf' => 'Peralatan tetap', '#bb6671' => 'Area tertutup', '#50667d' => 'Jalur akses', '#79cbc3' => 'Pintu'] as $color => $label)
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm" style="background:{{ $color }}"></span>{{ $label }}</span>
                    @endforeach
                </div>
                <p class="muted">Konfirmasi susunan dan akses ruangan dengan petugas kampus sebelum kegiatan.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="button button-secondary" x-bind:disabled="loading" x-text="loading ? 'Menghitung…' : 'Hitung ulang'">Hitung ulang</button>
                    <button type="button" class="button button-primary" x-bind:disabled="loading || error || result?.error" x-on:click="usePlan()">Gunakan denah <x-icon name="arrow-right" class="size-4" /></button>
                </div>
            </section>
        </form>
        <noscript><x-notice>Aktifkan JavaScript untuk membuat dan melampirkan denah.</x-notice></noscript>
    </div>
@endif
@endsection
