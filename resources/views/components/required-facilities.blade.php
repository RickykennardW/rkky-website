@props(['selected' => []])
<fieldset class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    <legend class="sr-only">Fasilitas yang diperlukan</legend>
    @foreach(\App\Models\Room::FACILITIES as $facility)
        <label class="flex min-h-10 items-center gap-2 text-sm">
            <input class="checkbox" type="checkbox" name="required_facilities[]" value="{{ $facility }}"
                   @checked(in_array($facility, (array) old('required_facilities', $selected), true))>
            {{ __('campusspace.facilities.'.$facility) }}
        </label>
    @endforeach
</fieldset>
