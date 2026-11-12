@props(['name'])
<i data-lucide="{{ $name }}" aria-hidden="true" {{ $attributes->class(['shrink-0', 'size-5' => !$attributes->has('class')]) }}></i>
