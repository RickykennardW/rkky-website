@extends('layouts.shell')
@section('title', 'Equipment loans')

@section('content')
<x-heading title="Equipment loans" description="Track pickups, overdue items and recorded returns." />
<nav aria-label="Filter equipment loans" class="flex flex-wrap gap-2">
    @foreach([null, 'active', 'overdue', 'returned'] as $option)
        <a href="{{ route('admin.loans.index', $option ? ['status' => $option] : []) }}" class="button capitalize {{ $status === $option ? 'button-secondary text-accent' : 'button-ghost' }}" @if($status === $option) aria-current="page" @endif>{{ $option ?? 'All' }}</a>
    @endforeach
</nav>
@if($loans->count())
    <ul class="panel divide-y divide-line">
        @foreach($loans as $loan)
            @php($state = $loan->returned_at ? 'returned' : ($loan->booking->ends_at <= now() ? 'overdue' : 'active'))
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="break-words text-sm font-semibold">{{ $loan->equipment?->name }} · {{ $loan->quantity }} units</p>
                    <p class="muted mt-1">{{ $loan->booking->title }} · {{ $loan->booking->profile?->full_name }}</p>
                    @if($loan->allocation_warning)<p class="mt-1 text-xs text-warning">Stock loss affects approved allocations</p>@endif
                </div>
                <div class="flex items-center gap-3"><x-status :status="$state" /><a href="{{ route('admin.bookings.show', $loan->booking) }}" class="button button-ghost">View booking <x-icon name="arrow-right" class="size-4" /></a></div>
            </li>
        @endforeach
    </ul>
    {{ $loans->links() }}
@else
    <x-empty icon="package-check" title="No matching loans" description="Equipment loans appear here after pickup." />
@endif
@endsection
