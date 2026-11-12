<?php

namespace App\Http\Requests;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,100'],
            'category' => ['required', Rule::in(Equipment::CATEGORIES)],
            'description' => ['required', 'string', 'max:1500'],
            'total_quantity' => ['required', 'integer', 'between:0,10000'],
            'unusable_quantity' => ['required', 'integer', 'min:0', 'lte:total_quantity'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
        ];
    }
}
