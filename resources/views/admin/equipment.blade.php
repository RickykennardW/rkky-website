@extends('layouts.shell')
@section('title', 'Manage equipment')

@section('content')
<x-heading title="Manage equipment" description="Maintain usable stock, availability and equipment categories.">
    <a href="{{ route('admin.equipment.create') }}" class="button button-primary"><x-icon name="plus" class="size-4" /> Add equipment</a>
</x-heading>
@if($equipment->count())
    <ul class="panel divide-y divide-line">
        @foreach($equipment as $item)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-0"><h2 class="break-words text-sm font-semibold">{{ $item->name }}</h2><p class="muted mt-1">{{ $item->category }} · {{ $item->usableQuantity() }} usable of {{ $item->total_quantity }}</p></div>
                <div class="flex items-center gap-3"><x-status :status="$item->status" /><a class="button button-ghost" href="{{ route('admin.equipment.edit', $item) }}">Edit <x-icon name="pencil" class="size-4" /></a></div>
            </li>
        @endforeach
    </ul>
    {{ $equipment->links() }}
@else
    <x-empty icon="boxes" title="No equipment in the catalog" description="Add the first equipment type." :href="route('admin.equipment.create')" action="Add equipment" />
@endif
@endsection
