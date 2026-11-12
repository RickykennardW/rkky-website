@extends('layouts.shell')
@section('title', 'Manage rooms')

@section('content')
<x-heading title="Manage rooms" description="Set campus room capacity, facilities and planner geometry.">
    <a href="{{ route('admin.rooms.create') }}" class="button button-primary"><x-icon name="plus" class="size-4" /> Add room</a>
</x-heading>
@if($rooms->count())
    <ul class="panel divide-y divide-line">
        @foreach($rooms as $room)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0"><h2 class="break-words text-sm font-semibold">{{ $room->name }}</h2><p class="muted mt-1">{{ $room->location }} · {{ $room->capacity }} people</p></div>
                <div class="flex items-center gap-3"><x-status :status="$room->active ? 'active' : 'unavailable'" /><a class="button button-ghost" href="{{ route('admin.rooms.edit', $room) }}">Edit <x-icon name="pencil" class="size-4" /></a></div>
            </li>
        @endforeach
    </ul>
    {{ $rooms->links() }}
@else
    <x-empty icon="building-2" title="No rooms in the catalog" description="Add the first campus room." :href="route('admin.rooms.create')" action="Add room" />
@endif
@endsection
