@props(['href', 'label', 'current'])
<nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-muted">
    <a href="{{ $href }}" class="hover:text-ink">{{ $label }}</a>
    <x-icon name="chevron-right" class="size-4" />
    <span class="text-ink">{{ $current }}</span>
</nav>
