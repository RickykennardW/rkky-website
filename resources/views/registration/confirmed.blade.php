<x-registration-receipt title="Registration Confirmed" :heading="$alreadyVerified ? 'Already confirmed.' : 'Registration confirmed.'">
    <p class="receipt-description">
        @if ($alreadyVerified)
            Your email has already been verified. Your registration for Cloud Computing 2025 is confirmed.
        @else
            Your email has been verified. Your registration for Cloud Computing 2025 is now confirmed.
        @endif
    </p>
    <dl class="receipt-details grid">
        <div><dt>Course</dt><dd>Cloud Computing 2025</dd></div>
        <div><dt>Status</dt><dd>Email verified</dd></div>
    </dl>
    <p class="receipt-note">You're all set. Thank you for confirming your registration.</p>
    <a class="submit-button flex w-full items-center justify-center" href="{{ route('registration.index') }}">Back to registration</a>
</x-registration-receipt>
