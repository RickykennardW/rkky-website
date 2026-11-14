@props(['bookings', 'admin' => false, 'empty' => 'Belum ada peminjaman', 'newBooking' => true])
@if(count($bookings))
    <ul class="panel divide-y divide-line">
        @foreach($bookings as $booking)
            <li>
                <a href="{{ route($admin ? 'admin.bookings.show' : 'bookings.show', $booking) }}" class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-4 py-4 transition-colors hover:bg-raised sm:flex sm:gap-4 sm:px-5">
                    <span class="min-w-0 flex-1">
                        <span class="block break-words text-sm font-semibold">{{ $booking->title }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-muted">{{ $booking->room?->name ?? 'Hanya peralatan' }} · {{ $booking->period_label }}</span>
                        @if($admin)<span class="mt-1 block text-xs text-muted">{{ $booking->profile?->full_name }}</span>@endif
                    </span>
                    <x-status :status="$booking->status" class="col-start-1 row-start-2 w-fit sm:ml-auto" />
                    <x-icon name="arrow-right" class="col-start-2 row-start-1 size-4 text-muted" />
                </a>
            </li>
        @endforeach
    </ul>
@else
    <x-empty :title="$empty" :href="!$admin && $newBooking ? route('rooms.index') : null" action="Cari Ruangan" />
@endif
