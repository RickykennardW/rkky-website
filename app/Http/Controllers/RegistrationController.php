<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistrationRequest;
use App\Mail\RegistrationConfirmation;
use App\Models\Registration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegistrationController extends Controller
{
    public function index(): View
    {
        return view('registration.index');
    }

    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['full_name', 'email', 'password', 'birthdate']);
        $data['password'] = Hash::make($data['password']);

        try {
            $registration = Registration::query()->create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => StoreRegistrationRequest::EMAIL_ALREADY_REGISTERED,
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('registration_id', $registration->id);

        $emailDelivery = 'unavailable';

        try {
            $sentMessage = Mail::to($registration->email)->send(new RegistrationConfirmation($registration));

            if (config('mail.default') === 'smtp') {
                $emailDelivery = $sentMessage !== null ? 'sent' : 'failed';
            }
        } catch (Throwable $exception) {
            // Transport and configuration errors can contain credentials; never log the exception or its message.
            Log::warning('Registration confirmation delivery failed.', [
                'registration_id' => $registration->id,
                'exception_type' => $exception::class,
            ]);
            $emailDelivery = 'failed';
        }

        $request->session()->put('registration_email_delivery', $emailDelivery);

        return to_route('registration.success');
    }

    public function success(Request $request): Response|RedirectResponse
    {
        $registrationId = $request->session()->get('registration_id');

        if (! is_int($registrationId)) {
            return to_route('registration.index');
        }

        $registration = Registration::query()->find($registrationId, ['full_name', 'email_verified_at']);

        if ($registration === null) {
            return to_route('registration.index');
        }

        return response()->view('registration.success', [
            'registrationName' => $registration->full_name,
            'emailDelivery' => $request->session()->get('registration_email_delivery', 'unavailable'),
            'emailVerified' => $registration->email_verified_at !== null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(string $registration): Response
    {
        try {
            $reference = explode('|', Crypt::decryptString($registration), 2);
        } catch (DecryptException) {
            return $this->invalidConfirmation();
        }

        $registrationId = filter_var($reference[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($registrationId === false || count($reference) !== 2) {
            return $this->invalidConfirmation();
        }

        $registrationRecord = Registration::query()->find($registrationId, ['id', 'email', 'email_verified_at']);

        if ($registrationRecord === null) {
            return $this->invalidConfirmation(404);
        }

        if (! hash_equals(hash('sha256', $registrationRecord->email), $reference[1])) {
            return $this->invalidConfirmation();
        }

        $updated = Registration::query()->whereKey($registrationId)->where('email', $registrationRecord->email)->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
            'status' => 'verified',
        ]);

        if ($updated === 0 && ! Registration::query()->whereKey($registrationId)
            ->where('email', $registrationRecord->email)->whereNotNull('email_verified_at')->exists()) {
            return $this->invalidConfirmation();
        }

        return response()->view('registration.confirmed', [
            'alreadyVerified' => $updated === 0,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function invalidConfirmation(int $status = 403): Response
    {
        return response()->view('registration.confirmation-invalid', status: $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
