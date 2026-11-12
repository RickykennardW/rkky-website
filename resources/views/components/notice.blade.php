@props(['tone' => 'info'])
@php($color = match($tone) {
    'error' => 'border-[#623c46] bg-[#301d24] text-negative',
    'success' => 'border-[#315044] bg-[#152820] text-positive',
    default => 'border-[#2e4260] bg-[#132135] text-[#c4d7f5]',
})
<div {{ $attributes->class(['flex items-start gap-3 rounded-lg border px-4 py-3 text-sm leading-relaxed', $color]) }} role="{{ $tone === 'error' ? 'alert' : 'status' }}">
    <x-icon :name="$tone === 'success' ? 'circle-check' : 'circle-alert'" class="mt-0.5 size-4" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
