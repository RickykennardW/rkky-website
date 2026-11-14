@extends('layouts.shell')
@section('title', 'Notifikasi')

@section('content')
<x-heading title="Notifikasi" description="Pembaruan pengajuan dan peminjaman peralatan.">
    @if($notifications->count())
        <form method="post" action="{{ route('notifications.read') }}">@csrf<button class="button button-secondary">Tandai semua sudah dibaca</button></form>
    @endif
</x-heading>
@if($notifications->count())
    <ul class="panel divide-y divide-line">
        @foreach($notifications as $notification)
            <li>
                <a class="flex items-center gap-3 p-4 hover:bg-raised" href="{{ route('bookings.show', $notification->data['booking_id']) }}">
                    <x-icon name="bell" class="size-4 text-accent" />
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-sm font-semibold">{{ $notification->data['title'] }}</p>
                        <p class="muted mt-1">{{ $notification->data['message'] }}</p>
                    </div>
                    @unless($notification->read_at)<span class="chip">New</span>@endunless
                </a>
            </li>
        @endforeach
    </ul>
    {{ $notifications->links() }}
@else
    <x-empty icon="bell" title="Belum ada notifikasi" description="Updates to your bookings will appear here." />
@endif
@endsection
