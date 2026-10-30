<?php

namespace App\Http\Requests;

use App\Models\Registration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public const EMAIL_ALREADY_REGISTERED = 'This email address is already registered for Cloud Computing 2025.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email', Rule::unique(Registration::class, 'email')],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['bail', 'required', 'string'],
            'birthdate' => ['bail', 'required', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Enter your full name.',
            'full_name.string' => 'Enter your full name as text.',
            'full_name.min' => 'Your full name must be at least 3 characters.',
            'full_name.max' => 'Your full name cannot exceed 150 characters.',
            'email.required' => 'Enter your student email address.',
            'email.string' => 'Enter a valid email address.',
            'email.email' => 'Enter a valid email address, such as you@university.edu.',
            'email.max' => 'Your email address cannot exceed 255 characters.',
            'email.unique' => self::EMAIL_ALREADY_REGISTERED,
            'password.required' => 'Create a password.',
            'password.string' => 'Enter your password as text.',
            'password.min' => 'Use at least 8 characters for your password.',
            'password.confirmed' => 'Your passwords do not match.',
            'password_confirmation.required' => 'Re-enter your password.',
            'password_confirmation.string' => 'Re-enter your password as text.',
            'birthdate.required' => 'Select your birthdate.',
            'birthdate.date' => 'Enter a valid birthdate.',
            'birthdate.before' => 'Your birthdate must be in the past.',
        ];
    }
}
