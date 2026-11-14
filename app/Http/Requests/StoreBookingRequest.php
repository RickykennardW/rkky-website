<?php

namespace App\Http\Requests;

use App\Services\BookingPeriod;
use App\Services\SchedulingRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('end_date') && $this->filled('date')) {
            $this->merge(['end_date' => $this->input('date')]);
        }
        $quantities = $this->input('quantities');
        if (is_array($quantities)) {
            $this->merge(['quantities' => array_filter($quantities, fn ($quantity) => ! in_array($quantity, [null, '', 0, '0'], true))]);
        }
    }

    public function rules(): array
    {
        return [
            ...BookingPeriod::rules(),
            'room_id' => ['nullable', 'uuid', ...(config('campusspace.demo') ? [] : ['exists:rooms,id'])],
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['required', 'string', 'min:2', 'max:2000'],
            'participants' => ['required', 'integer', 'between:1,500'],
            'committee' => ['required', 'integer', 'between:0,500'],
            'quantities' => ['nullable', 'array', 'max:100'],
            'quantities.*' => ['required', 'integer', 'between:1,10000'],
            'planner' => ['nullable', 'string', 'max:16000'],
            ...SchedulingRules::facilityRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Isi nama kegiatan.',
            'title.min' => 'Nama kegiatan minimal 2 karakter.',
            'description.required' => 'Jelaskan keperluan peminjaman.',
            'description.min' => 'Keperluan minimal 2 karakter.',
            'date.date_format' => 'Pilih tanggal mulai yang valid.',
            'end_date.date_format' => 'Pilih tanggal selesai yang valid.',
            'start.date_format' => 'Isi jam mulai yang valid.',
            'end.date_format' => 'Isi jam selesai yang valid.',
            'participants.between' => 'Jumlah peserta harus antara 1 dan 500.',
            'committee.between' => 'Jumlah panitia harus antara 0 dan 500.',
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
                $validator->errors()->add('room_id', 'Pilih ruangan atau setidaknya satu peralatan.');
            }
            if (! $this->filled('room_id') && ! empty($this->input('required_facilities'))) {
                $validator->errors()->add('required_facilities', 'Pilih ruangan untuk fasilitas yang diminta.');
            }

            foreach (array_keys($quantities) as $id) {
                if (! is_string($id) || ! Str::isUuid($id)) {
                    $validator->errors()->add('quantities', 'Pilihan peralatan tidak valid.');
                    break;
                }
            }
        }];
    }
}
