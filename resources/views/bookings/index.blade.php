@extends('layouts.shell')
@section('title', $admin ? 'Booking requests' : 'My bookings')

@section('content')
<x-heading :title="$admin ? 'Booking requests' : 'My bookings'" :description="$admin ? 'Review campus reservations and equipment requests.' : 'Track your room reservations and equipment borrowing history.'">
    @unless($admin)<a href="{{ route('bookings.create') }}" class="button button-primary"><x-icon name="calendar-plus" class="size-4" /> New booking</a>@endunless
</x-heading>
<nav aria-label="Filter bookings by status" class="flex flex-wrap gap-2">
    @foreach([null, ...$statuses] as $option)
        <a href="{{ route($admin ? 'admin.bookings.index' : 'bookings.index', $option ? ['status' => $option] : []) }}"
           class="button capitalize {{ $status === $option ? 'button-secondary text-accent' : 'button-ghost' }}" @if($status === $option) aria-current="page" @endif>{{ $option ?? 'All' }}</a>
    @endforeach
</nav>
<x-booking-list :bookings="$bookings" :admin="$admin" />
{{ $bookings->links() }}
@endsection
