@extends('layouts.shell')
@section('title', $equipment ? 'Edit equipment' : 'Add equipment')

@section('content')
<x-breadcrumb :href="route('admin.equipment.index')" label="Manage equipment" :current="$equipment ? 'Edit equipment' : 'Add equipment'" />
<x-heading :title="$equipment ? 'Edit '.$equipment->name : 'Add equipment'" description="Update stock and service status. Approved allocations are checked when stock changes." />
<form method="post" action="{{ route('admin.equipment.save', $equipment ? [$equipment] : []) }}" class="panel grid gap-4 p-5 sm:p-6">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="label">Name<input class="input" name="name" required minlength="2" maxlength="100" value="{{ old('name', $equipment?->name) }}"></label>
        <label class="label">Category<select class="input" name="category" required>
            <option value="">Select category</option>
            @foreach($categories as $category)<option @selected(old('category', $equipment?->category) === $category)>{{ $category }}</option>@endforeach
        </select></label>
    </div>
    <label class="label">Description<textarea class="input min-h-20" name="description" required maxlength="1500">{{ old('description', $equipment?->description) }}</textarea></label>
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="label">Total quantity<input class="input" name="total_quantity" type="number" min="0" max="10000" required value="{{ old('total_quantity', $equipment?->total_quantity ?? 0) }}"></label>
        <label class="label">Unusable quantity<input class="input" name="unusable_quantity" type="number" min="0" max="10000" required value="{{ old('unusable_quantity', $equipment?->unusable_quantity ?? 0) }}"></label>
        <label class="label">Status<select class="input" name="status" required>
            @foreach($statuses as $state)<option value="{{ $state }}" @selected(old('status', $equipment?->status ?? 'available') === $state)>{{ ucfirst($state) }}</option>@endforeach
        </select></label>
    </div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary"><x-icon name="circle-check" class="size-4" /> Save equipment</button><a href="{{ route('admin.equipment.index') }}" class="button button-ghost">Cancel</a></div>
</form>
@endsection
