<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Services\RoomGeometry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    protected function prepareForValidation(): void
    {
        $layout = $this->input('layout');
        if (is_array($layout)) {
            foreach (['blocked', 'fixed'] as $kind) {
                if (is_string($layout[$kind] ?? null)) {
                    $layout[$kind] = json_decode($layout[$kind], true);
                }
            }
        }

        $this->merge(['active' => $this->boolean('active'), 'layout' => $layout]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,100'],
            'location' => ['required', 'string', 'between:2,120'],
            'description' => ['required', 'string', 'max:1500'],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'chairs' => ['required', 'integer', 'between:0,500'],
            'tables_count' => ['required', 'integer', 'between:0,500'],
            'active' => ['required', 'boolean'],
            'photo' => ['nullable', Rule::in(['seminar', 'classroom', 'hall'])],
            'facilities' => ['nullable', 'array', 'max:6'],
            'facilities.*' => ['required', Rule::in(Room::FACILITIES), 'distinct'],
            ...RoomGeometry::rules(),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! RoomGeometry::insideRoom($this->input('layout'))) {
                $validator->errors()->add('layout', 'The door, clearance and zones must fit inside the configured dimensions.');
            }
        }];
    }
}
