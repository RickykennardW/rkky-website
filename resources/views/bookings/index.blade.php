@extends('layouts.shell')
@section('title', $admin ? 'Permintaan Peminjaman' : 'Peminjaman Saya')

@section('content')
<x-heading :title="$admin ? 'Permintaan Peminjaman' : 'Peminjaman Saya'" :description="$admin ? 'Tinjau pengajuan ruangan dan peralatan.' : 'Lihat status dan riwayat pengajuanmu.'">
    @unless($admin)<a href="{{ route('rooms.index') }}" class="button button-primary"><x-icon name="search" class="size-4" /> Cari Ruangan</a>@endunless
</x-heading>
<nav aria-label="Filter status peminjaman" class="flex flex-wrap gap-2">
    @foreach([null, ...$statuses] as $option)
        <a href="{{ route($admin ? 'admin.bookings.index' : 'bookings.index', $option ? ['status' => $option] : []) }}"
           class="button {{ $status === $option ? 'button-secondary text-accent' : 'button-ghost' }}" @if($status === $option) aria-current="page" @endif>{{ $option ? __('campusspace.statuses.'.$option) : 'Semua' }}</a>
    @endforeach
</nav>
<x-booking-list :bookings="$bookings" :admin="$admin" />
{{ $bookings->links() }}
@endsection
