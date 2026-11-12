<?php

namespace App\Http\Requests;

use App\Services\BookingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $quantities = $this->input('quantities');
        if (is_array($quantities)) {
            $this->merge(['quantities' => array_filter($quantities, fn ($quantity) => ! in_array($quantity, [null, '', 0, '0'], true))]);
        }
    }

    public function rules(): array
    {
        return [
            ...BookingPeriod::rules(),
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['required', 'string', 'min:2', 'max:2000'],
            'participants' => ['required', 'integer', 'between:1,500'],
            'committee' => ['required', 'integer', 'between:0,500'],
            'quantities' => ['nullable', 'array', 'max:100'],
            'quantities.*' => ['required', 'integer', 'between:1,10000'],
            'planner' => ['nullable', 'string', 'max:16000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $quantities = $this->input('quantities') ?? [];
            if (! $this->filled('room_id') && $quantities === []) {
                $validator->errors()->add('room_id', 'Choose a room or at least one equipment item.');
            }

            foreach (array_keys($quantities) as $id) {
                if (! is_string($id) || ! Str::isUuid($id)) {
                    $validator->errors()->add('quantities', 'An equipment selection is invalid.');
                    break;
                }
            }
        }];
    }
}
