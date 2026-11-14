@extends('layouts.shell')
@section('title', 'Peralatan')

@section('content')
<x-heading title="Peralatan Kampus" description="Cek stok dan pilih peralatan yang ingin dipinjam." />
<form method="get" action="{{ route('equipment.index') }}" class="panel grid gap-4 p-4 sm:p-5">
    <div class="flex flex-wrap items-center gap-2 text-sm font-medium"><x-icon name="calendar-range" class="size-4 text-accent" />Cek jadwal peminjaman <span class="ml-auto text-xs text-muted">Asia/Jakarta · WIB</span></div>
    <x-period-fields :period="$filter" />
    <div class="flex flex-wrap items-end gap-3">
        <label class="label min-w-40">Kategori
            <select name="category" class="input">
                <option value="">Semua kategori</option>
                @foreach($categories as $category)<option @selected(($filter['category'] ?? null) === $category)>{{ $category }}</option>@endforeach
            </select>
        </label>
        <label class="chip min-h-11 cursor-pointer"><input class="checkbox" type="checkbox" name="available" value="1" @checked(!empty($filter['available']))> Hanya yang tersedia</label>
        <div class="ml-auto flex gap-2"><a href="{{ route('equipment.index') }}" class="button button-ghost">Hapus</a><button class="button button-primary"><x-icon name="search" class="size-4" /> Cari peralatan</button></div>
    </div>
</form>
@if($equipment->count())
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($equipment as $item)
            @php($stock = $availability === null ? $item->usableQuantity() : ($availability[$item->id] ?? 0))
            <article class="panel grid content-start gap-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="grid size-11 place-items-center rounded-lg border border-[#304362] bg-[#18263b] text-accent"><x-icon :name="match($item->category) { 'Audio' => 'audio-lines', 'Presentation' => 'presentation', 'Furniture' => 'grid-2x2', 'Power' => 'cable', default => 'package' }" /></span>
                    <span class="chip">{{ $item->category }}</span>
                </div>
                <div><h2 class="section-title">{{ $item->name }}</h2><p class="muted mt-1">{{ $item->description }}</p></div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
                    <span>{{ $item->total_quantity }} unit</span>
                    <x-status :status="$item->status !== 'available' ? $item->status : ($stock ? ($availability === null ? 'check schedule' : 'available') : 'reserved')" />
                    @if($availability !== null)<span class="font-medium text-ink">{{ $stock }} unit tersedia pada jadwal ini</span>@endif
                </div>
                @if($stock > 0)
                    <a class="button button-ghost justify-self-start" href="{{ route('bookings.create', ['equipment' => $item->id, ...array_filter(Arr::only($filter, ['date', 'start', 'end_date', 'end']))]) }}">Pilih peralatan <x-icon name="arrow-right" class="size-4" /></a>
                @endif
            </article>
        @endforeach
    </div>
    {{ $equipment->links() }}
@else
    <x-empty icon="boxes" title="Belum ada peralatan yang cocok" description="Pilih kategori atau jadwal lain." :href="route('equipment.index')" action="Hapus filter" />
@endif
@endsection
