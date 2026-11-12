<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('booking'));
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:equipment_id,missing,damaged'],
            'items.*.equipment_id' => ['required', 'uuid', 'distinct'],
            'items.*.missing' => ['required', 'integer', 'between:0,10000'],
            'items.*.damaged' => ['required', 'integer', 'between:0,10000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
