@props(['selected' => []])
<fieldset class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    <legend class="mb-3 text-sm font-semibold">Required room facilities</legend>
    @foreach(\App\Models\Room::FACILITIES as $facility)
        <label class="flex items-center gap-2 text-sm">
            <input class="checkbox" type="checkbox" name="required_facilities[]" value="{{ $facility }}"
                   @checked(in_array($facility, (array) old('required_facilities', $selected), true))>
            {{ $facility }}
        </label>
    @endforeach
</fieldset>
