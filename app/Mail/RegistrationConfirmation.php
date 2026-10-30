<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class RegistrationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $studentName;

    public readonly string $studentEmail;

    public readonly string $registeredAt;

    public readonly string $expiresAt;

    public readonly string $confirmationUrl;

    public function __construct(Registration $registration)
    {
        $expiration = now()->addDays(7);
        $this->studentName = $registration->full_name;
        $this->studentEmail = $registration->email;
        $this->registeredAt = $registration->created_at->format('F j, Y');
        $this->expiresAt = $expiration->format('F j, Y, g:i A T');
        $this->confirmationUrl = URL::temporarySignedRoute('registration.confirm', $expiration, [
            'registration' => Crypt::encryptString($registration->id.'|'.hash('sha256', $registration->email)),
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cloud Computing 2025 — Confirm Your Registration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-confirmation',
            text: 'emails.registration-confirmation-text',
        );
    }
}
