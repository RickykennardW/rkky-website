<x-registration-receipt title="Registration Received" heading="Registration received.">
    <p class="receipt-description">
        @if ($emailVerified)
            Your email has been verified. Your registration for Cloud Computing 2025 is confirmed.
        @elseif ($emailDelivery === 'sent')
            Your registration has been received. Please check your email to confirm your registration.
        @else
            Your registration has been received and your details are saved.
        @endif
    </p>

    <dl class="receipt-details grid">
        <div><dt>Registration name</dt><dd>{{ $registrationName }}</dd></div>
        <div><dt>Course</dt><dd>Cloud Computing 2025</dd></div>
        <div><dt>Status</dt><dd class="receipt-status">{{ $emailVerified ? 'Email verified' : 'Pending email verification' }}</dd></div>
    </dl>

    <p class="receipt-note">
        @if ($emailVerified)
            Your registration is complete. Thank you for confirming your email address.
        @elseif ($emailDelivery === 'sent')
            Your confirmation link expires in 7 days. Check your spam folder if you don't see the email.
        @elseif ($emailDelivery === 'failed')
            We couldn't send your confirmation email. Your registration is saved. Please contact the course organizer for help.
        @else
            Email delivery is not enabled for this environment. Your registration is saved and is awaiting confirmation.
        @endif
    </p>
    <a class="submit-button flex w-full items-center justify-center" href="{{ route('registration.index') }}">Back to registration</a>
</x-registration-receipt>
