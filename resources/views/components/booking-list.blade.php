@props(['bookings', 'admin' => false, 'empty' => 'No bookings found', 'newBooking' => true])
@if(count($bookings))
    <ul class="panel divide-y divide-line">
        @foreach($bookings as $booking)
            <li>
                <a href="{{ route($admin ? 'admin.bookings.show' : 'bookings.show', $booking) }}" class="flex flex-wrap items-center gap-3 px-4 py-4 transition-colors hover:bg-raised sm:gap-5 sm:px-5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg border border-line bg-raised text-accent"><x-icon name="calendar-days" class="size-4" /></span>
                    <span class="min-w-0 flex-1 basis-[60%] sm:basis-auto">
                        <span class="block break-words text-sm font-semibold">{{ $booking->title }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-muted">{{ $booking->room?->name ?? 'Equipment only' }} · {{ $booking->period_label }}</span>
                        @if($admin)<span class="mt-1 block text-xs text-muted">{{ $booking->profile?->full_name }}</span>@endif
                    </span>
                    <x-status :status="$booking->status" class="ml-auto" />
                    <x-icon name="arrow-right" class="size-4 text-muted" />
                </a>
            </li>
        @endforeach
    </ul>
@else
    <x-empty :title="$empty" description="Reservations matching this view will appear here." :href="!$admin && $newBooking ? route('bookings.create') : null" action="New booking" />
@endif
