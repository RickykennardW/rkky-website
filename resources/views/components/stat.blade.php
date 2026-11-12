@props(['icon', 'label', 'count', 'detail'])
<div class="panel flex items-start gap-3 p-4 sm:gap-4 sm:p-5">
    <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-[#304362] bg-[#18263b] text-accent"><x-icon :name="$icon" class="size-[18px]" /></span>
    <div class="min-w-0">
        <div class="text-xs font-medium text-muted">{{ $label }}</div>
        <div class="mt-1 text-[1.65rem] leading-tight font-semibold tracking-tight tabular-nums">{{ $count }}</div>
        <div class="mt-1.5 text-xs text-muted">{{ $detail }}</div>
    </div>
</div>
