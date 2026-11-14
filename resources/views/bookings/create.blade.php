@extends('layouts.shell')
@section('title', 'Ajukan Peminjaman')

@section('content')
<x-heading title="Ajukan Peminjaman" description="Periksa jadwal dan isi informasi kegiatan. Admin akan meninjau pengajuanmu." />
@php
    $roomId = old('room_id', $selectedRoom?->id);
    $attachedPlan = old('planner', $plan ? json_encode($plan, JSON_THROW_ON_ERROR) : '');
    $initialQuantities = (array) old('quantities', $requirements['quantities'] ?? []);
@endphp
<form method="post" action="{{ route('bookings.store') }}" class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)]"
      x-data="bookingForm" x-on:change="changed($event)" x-on:input="readSummary()"
      data-availability-url="{{ route('availability') }}" data-rooms-url="{{ route('rooms.index') }}"
      data-scheduling-url="{{ route('scheduling.index') }}" data-preview-url="{{ route('planner.preview') }}">
    @csrf
    <input type="hidden" name="planner" value="{{ $attachedPlan }}" x-ref="plan">
    <div class="grid min-w-0 gap-4">
        <section class="panel grid min-w-0 gap-4 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="section-title">Ruangan dan jadwal</h2><span class="text-xs text-muted">Waktu WIB</span>
            </div>
            <label class="label">Ruangan
                <select class="input @error('room_id') border-negative @enderror" name="room_id" @error('room_id') aria-invalid="true" @enderror>
                    <option value="">Hanya peralatan</option>
                    @foreach($rooms as $room)<option data-capacity="{{ $room->capacity }}" value="{{ $room->id }}" @selected($roomId === $room->id)>{{ $room->name }} · {{ $room->capacity }} orang</option>@endforeach
                </select>
                @error('room_id')<span class="text-xs text-negative">{{ $message }}</span>@enderror
            </label>
            <x-period-fields :period="$period" required compact />
            <div class="grid gap-2" x-cloak>
                <p role="status" class="text-sm" x-bind:class="availabilityError ? 'text-negative' : 'text-positive'" x-text="checking ? 'Memeriksa jadwal…' : availabilityMessage"></p>
                <div class="flex flex-wrap gap-2" x-show="roomUnavailable">
                    <a x-bind:href="alternativeUrl('time')" class="button button-secondary">Lihat waktu lain</a>
                    <a x-bind:href="alternativeUrl('room')" class="button button-ghost">Cari ruangan lain</a>
                </div>
                <button type="button" x-show="availabilityError && !roomUnavailable" x-on:click="check()" class="button button-ghost justify-self-start">Periksa ulang</button>
            </div>
        </section>
        <section class="panel grid min-w-0 gap-4 p-4 sm:p-6">
            <h2 class="section-title">Informasi kegiatan</h2>
            <label class="label">Nama kegiatan
                <input class="input @error('title') border-negative @enderror" name="title" required minlength="2" maxlength="120" value="{{ old('title') }}" placeholder="Misalnya, rapat panitia" @error('title') aria-invalid="true" @enderror>
                @error('title')<span class="text-xs text-negative">{{ $message }}</span>@enderror
            </label>
            <label class="label">Keperluan
                <textarea class="input min-h-20 resize-y @error('description') border-negative @enderror" name="description" required minlength="2" maxlength="2000" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                @error('description')<span class="text-xs text-negative">{{ $message }}</span>@enderror
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="label">Jumlah peserta
                    <input class="input" name="participants" type="number" min="1" max="500" required value="{{ old('participants', $requirements['participants'] ?? $plan['participants'] ?? 1) }}">
                    @error('participants')<span class="text-xs text-negative">{{ $message }}</span>@enderror
                </label>
                <label class="label">Jumlah panitia
                    <input class="input" name="committee" type="number" min="0" max="500" required value="{{ old('committee', $requirements['committee'] ?? $plan['committee'] ?? 0) }}">
                    @error('committee')<span class="text-xs text-negative">{{ $message }}</span>@enderror
                </label>
            </div>
        </section>
        <section class="panel divide-y divide-line" aria-label="Tambahan opsional">
            <details class="p-4 sm:px-6" x-ref="equipment" @if($selectedEquipment || array_filter($initialQuantities) || $errors->hasAny(['quantities', 'quantities.*'])) open @endif>
                <summary class="min-h-8 py-1 font-medium">Tambahkan peralatan <span class="ml-1 text-xs font-normal text-muted">Opsional</span></summary>
                <ul class="mt-2 divide-y divide-line">
                    @forelse($equipment as $item)
                        @php($maximum = $item->usableQuantity())
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0"><p class="break-words text-sm font-medium">{{ $item->name }}</p><p class="mt-1 text-xs text-muted" x-text="quantityLabel('{{ $item->id }}', {{ $maximum }})">{{ $maximum }} unit siap pakai</p></div>
                            <label class="w-22 shrink-0"><span class="sr-only">Jumlah {{ $item->name }}</span>
                                <input class="input" type="number" min="0" max="{{ $maximum }}" x-bind:max="limits?.['{{ $item->id }}'] ?? {{ $maximum }}" x-on:invalid="$refs.equipment.open = true"
                                       name="quantities[{{ $item->id }}]" data-equipment-name="{{ $item->name }}"
                                       value="{{ $initialQuantities[$item->id] ?? ($selectedEquipment === $item->id && $maximum > 0 ? 1 : 0) }}">
                            </label>
                        </li>
                    @empty
                        <li class="muted py-2">Belum ada peralatan di katalog.</li>
                    @endforelse
                </ul>
                @error('quantities')<p class="mt-2 text-sm text-negative">{{ $message }}</p>@enderror
                @error('quantities.*')<p class="mt-2 text-sm text-negative">{{ $message }}</p>@enderror
            </details>
            <details class="p-4 sm:px-6" @if(old('required_facilities', $requirements['required_facilities'] ?? []) || $errors->has('required_facilities')) open @endif>
                <summary class="min-h-8 py-1 font-medium">Fasilitas yang diperlukan <span class="ml-1 text-xs font-normal text-muted">Opsional</span></summary>
                <div class="mt-3">
                    <x-required-facilities :selected="$requirements['required_facilities'] ?? []" />
                    @error('required_facilities')<p class="mt-2 text-sm text-negative">{{ $message }}</p>@enderror
                </div>
            </details>
            <details class="p-4 sm:px-6" x-show="roomId" x-cloak>
                <summary class="min-h-8 py-1 font-medium">Bantu atur ruangan <span class="ml-1 text-xs font-normal text-muted">Opsional</span></summary>
                <div class="mt-3 grid gap-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="label grow">Susunan kursi
                            <select class="input" x-ref="arrangement" x-on:change="clearPreview()">
                                <option value="">Saran otomatis</option>
                                @foreach(\App\Services\SmartRoomPlanner::ARRANGEMENTS as $layout)<option value="{{ $layout }}">{{ $layout }}</option>@endforeach
                            </select>
                        </label>
                        <button type="button" x-on:click="previewPlan()" x-bind:disabled="planning" class="button button-secondary" x-text="planning ? 'Menyiapkan…' : 'Lihat denah'">Lihat denah</button>
                    </div>
                    <p role="alert" class="text-sm text-negative" x-show="planError" x-text="planError"></p>
                    @error('planner')<p class="text-sm text-negative">{{ $message }}</p>@enderror
                    <div x-show="planPreview?.layout" class="rounded-lg border border-line bg-canvas p-3">
                        <svg x-ref="drawing" class="mx-auto h-60 max-w-full" role="img" aria-label="Denah susunan ruangan"></svg>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" x-show="planPreview?.layout && !planError" x-on:click="attachPlan()" class="button button-secondary">Gunakan denah</button>
                        <span x-show="hasPlan" class="text-sm text-positive">Denah terlampir. <button type="button" x-on:click="removePlan(); readSummary()" class="underline">Lepas denah</button></span>
                    </div>
                </div>
            </details>
        </section>
    </div>
    <aside class="panel grid min-w-0 gap-4 p-4 sm:p-6 xl:sticky xl:top-6" aria-labelledby="booking-summary">
        <h2 id="booking-summary" class="section-title">Ringkasan pengajuan</h2>
        <div class="grid gap-2 text-sm">
            <strong class="break-words" x-text="summary.title">{{ old('title', $selectedRoom?->name ?? 'Pengajuan baru') }}</strong>
            <p class="text-muted" x-text="summary.room"></p>
            <p class="text-muted" x-text="summary.period"></p>
            <p class="text-muted" x-text="summary.attendance"></p>
            <p class="break-words text-muted" x-show="summary.extras" x-text="summary.extras"></p>
        </div>
        <button type="submit" class="button button-primary" x-bind:disabled="roomUnavailable">Kirim pengajuan <x-icon name="arrow-right" class="size-4" /></button>
        <p class="text-xs leading-relaxed text-muted">Jadwal dikonfirmasi setelah admin menyetujui pengajuan.</p>
    </aside>
</form>
@endsection
