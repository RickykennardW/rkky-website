<x-registration-receipt title="Confirmation Link Unavailable" heading="This link is no longer valid." variant="invalid">
    <p class="receipt-description">This confirmation link is invalid or has expired. Your registration has not been changed.</p>
    <p class="receipt-note">Confirmation links expire after 7 days. Please contact the course organizer if you need help confirming your registration.</p>
    <a class="submit-button flex w-full items-center justify-center" href="{{ route('registration.index') }}">Back to registration</a>
</x-registration-receipt>
