@if(isset($errors) && $errors->any())
    <x-notice tone="error">
        @if($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul class="list-disc space-y-1 pl-4">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
    </x-notice>
@elseif(session('status'))
    <x-notice tone="success">{{ session('status') }}</x-notice>
@endif
