@props(['status'])
@php($color = match($status) {
    'approved', 'available', 'active' => 'bg-[#17332a] text-positive',
    'completed', 'returned' => 'bg-[#193044] text-[#a5cef6]',
    'pending', 'maintenance', 'overdue' => 'bg-[#382c1b] text-warning',
    'rejected', 'reserved', 'unavailable' => 'bg-[#3a252d] text-negative',
    default => 'bg-raised text-muted',
})
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize', $color]) }}>
    <span aria-hidden="true" class="size-1.5 rounded-full bg-current"></span>{{ \Illuminate\Support\Facades\Lang::get('campusspace.statuses.'.$status, [], 'en') }}
</span>
