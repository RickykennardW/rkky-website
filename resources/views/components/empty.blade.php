@props(['icon' => 'calendar-days', 'title', 'description' => null, 'href' => null, 'action' => null])
<div class="panel grid justify-items-center gap-3 px-5 py-10 text-center">
    <span class="rounded-lg border border-line bg-raised p-3 text-accent"><x-icon :name="$icon" /></span>
    <h2 class="font-semibold">{{ $title }}</h2>
    @if($description)<p class="muted max-w-md">{{ $description }}</p>@endif
    @if($href)<a class="button button-secondary mt-1" href="{{ $href }}">{{ $action }} <x-icon name="arrow-right" class="size-4" /></a>@endif
</div>
