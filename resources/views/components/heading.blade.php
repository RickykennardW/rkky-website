@props(['title', 'description' => null])
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if($description)<p class="muted mt-2">{{ $description }}</p>@endif
    </div>
    {{ $slot }}
</div>
