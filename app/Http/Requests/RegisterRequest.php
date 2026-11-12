<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed',
                function (string $attribute, string $password, \Closure $fail): void {
                    if (strlen($password) > 72) {
                        $fail('Choose a password with at most 72 bytes.');
                    }
                },
            ],
        ];
    }
}
