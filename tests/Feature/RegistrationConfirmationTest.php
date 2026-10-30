<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Registration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class RegistrationConfirmationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signed_url_verifies_only_its_registration(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
        $registration = Registration::factory()->create();
        $other = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);

        $this->get($mail->confirmationUrl)->assertOk()->assertSee('Registration confirmed.')
            ->assertDontSee($registration->email)->assertDontSee($registration->password);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'status' => 'verified',
            'email_verified_at' => '2026-10-30 12:00:00',
        ]);
        $this->assertDatabaseHas('registrations', ['id' => $other->id, 'status' => 'pending', 'email_verified_at' => null]);
        Mail::assertNothingOutgoing();
    }

    public function test_expired_url_does_not_verify(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        $this->travelTo(Carbon::parse('2026-11-06 12:00:01'));

        $this->get($mail->confirmationUrl)->assertForbidden()->assertSee('This link is no longer valid.');

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'pending', 'email_verified_at' => null]);
    }

    public function test_tampered_signature_does_not_verify(): void
    {
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        $url = preg_replace('/signature=[^&]+/', 'signature=invalid', $mail->confirmationUrl);

        $this->get($url)->assertForbidden()->assertSee('This link is no longer valid.');

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'email_verified_at' => null]);
    }

    public function test_changing_the_reference_invalidates_the_signature(): void
    {
        $registration = Registration::factory()->create();
        $other = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        $otherMail = new RegistrationConfirmation($other);
        $url = url(parse_url($otherMail->confirmationUrl, PHP_URL_PATH)).'?'.parse_url($mail->confirmationUrl, PHP_URL_QUERY);

        $this->get($url)->assertForbidden();

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'email_verified_at' => null]);
        $this->assertDatabaseHas('registrations', ['id' => $other->id, 'email_verified_at' => null]);
    }

    public function test_unsigned_link_does_not_verify(): void
    {
        $registration = Registration::factory()->create();

        $this->get(route('registration.confirm', ['registration' => $registration->id]))->assertForbidden();

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'email_verified_at' => null]);
    }

    public function test_signed_unreadable_reference_is_rejected(): void
    {
        $url = URL::temporarySignedRoute('registration.confirm', now()->addDay(), ['registration' => 'invalid-reference']);

        $this->get($url)->assertForbidden()->assertSee('This link is no longer valid.');

        $this->assertDatabaseCount('registrations', 0);
    }

    #[TestWith(['not-an-id|email-hash'])]
    #[TestWith(['1'])]
    public function test_malformed_encrypted_reference_is_rejected(string $reference): void
    {
        $url = URL::temporarySignedRoute('registration.confirm', now()->addDay(), ['registration' => Crypt::encryptString($reference)]);

        $this->get($url)->assertForbidden();

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_deleted_registration_returns_an_unavailable_page(): void
    {
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        $registration->delete();

        $this->get($mail->confirmationUrl)->assertNotFound()->assertSee('This link is no longer valid.');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_email_change_invalidates_the_previous_link(): void
    {
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        $registration->update(['email' => 'changed@example.edu']);

        $this->get($mail->confirmationUrl)->assertForbidden();

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'email' => 'changed@example.edu', 'email_verified_at' => null]);
    }

    public function test_already_verified_registration_preserves_the_original_timestamp(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
        $registration = Registration::factory()->verified()->create();
        $mail = new RegistrationConfirmation($registration);
        $this->travelTo(Carbon::parse('2026-10-30 13:00:00'));

        $this->get($mail->confirmationUrl)->assertOk()->assertSee('Already confirmed.');

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'status' => 'verified',
            'email_verified_at' => '2026-10-30 12:00:00',
            'updated_at' => '2026-10-30 12:00:00',
        ]);
    }

    public function test_concurrent_email_change_cannot_be_verified_by_the_previous_link(): void
    {
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        Registration::retrieved(function (Registration $record) use ($registration): void {
            if ($record->id === $registration->id) {
                Registration::query()->whereKey($record->id)->update(['email' => 'changed@example.edu']);
            }
        });

        $this->get($mail->confirmationUrl)->assertForbidden();

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'email' => 'changed@example.edu',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);
    }

    #[TestWith([TransportException::class])]
    #[TestWith([InvalidArgumentException::class])]
    public function test_mail_failure_keeps_the_registration_and_hides_technical_errors(string $exceptionClass): void
    {
        $secret = 'simulated-sensitive-smtp-error';
        Mail::shouldReceive('to')->once()->with('avery@example.edu')->andThrow(new $exceptionClass($secret));
        Log::shouldReceive('warning')->once()->with('Registration confirmation delivery failed.', Mockery::on(
            fn (array $context): bool => array_keys($context) === ['registration_id', 'exception_type']
                && $context['registration_id'] > 0 && $context['exception_type'] === $exceptionClass
        ));

        $this->followingRedirects()->post(route('registration.store'), $this->payload())
            ->assertOk()->assertSee("We couldn't send your confirmation email.", false)
            ->assertDontSee('Please check your email')->assertDontSee($secret);

        $this->assertDatabaseHas('registrations', ['email' => 'avery@example.edu', 'status' => 'pending', 'email_verified_at' => null]);
    }

    public function test_smtp_acceptance_shows_check_email_instructions(): void
    {
        config(['mail.default' => 'smtp']);
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->once()->with(Mockery::type(RegistrationConfirmation::class))
            ->andReturn(Mockery::mock(SentMessage::class));
        Mail::shouldReceive('to')->once()->with('avery@example.edu')->andReturn($pendingMail);

        $this->followingRedirects()->post(route('registration.store'), $this->payload())
            ->assertOk()->assertSee('Please check your email to confirm your registration.');

        $this->assertDatabaseHas('registrations', ['email' => 'avery@example.edu', 'status' => 'pending', 'email_verified_at' => null]);
    }

    public function test_null_mail_result_does_not_claim_smtp_delivery(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $this->followingRedirects()->post(route('registration.store'), $this->payload())
            ->assertOk()->assertSee("We couldn't send your confirmation email.", false)->assertDontSee('Please check your email');

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_log_mailer_does_not_claim_email_delivery(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->followingRedirects()->post(route('registration.store'), $this->payload())
            ->assertOk()->assertSee('Email delivery is not enabled for this environment.')
            ->assertDontSee('Please check your email');

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_duplicate_registration_cannot_send_a_second_email(): void
    {
        Mail::fake();
        $this->post(route('registration.store'), $this->payload())->assertRedirectToRoute('registration.success');

        $this->from(route('registration.index'))->post(route('registration.store'), $this->payload())
            ->assertSessionHasErrors('email');

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_confirmation_requests_are_rate_limited(): void
    {
        $this->freezeTime();
        $registration = Registration::factory()->create();
        $mail = new RegistrationConfirmation($registration);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->get($mail->confirmationUrl)->assertOk();
        }

        $this->get($mail->confirmationUrl)->assertTooManyRequests();

        $this->assertSame('verified', $registration->fresh()->status);
    }

    public function test_receipt_reflects_verification_instead_of_stale_delivery_status(): void
    {
        $registration = Registration::factory()->verified()->create();

        $this->withSession(['registration_id' => $registration->id, 'registration_email_delivery' => 'failed'])
            ->get(route('registration.success'))->assertOk()->assertSee('Your registration is complete.')
            ->assertDontSee('Pending email verification')->assertDontSee("We couldn't send", false);
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'full_name' => 'Avery Example',
            'email' => 'avery@example.edu',
            'password' => 'test-password-2025',
            'password_confirmation' => 'test-password-2025',
            'birthdate' => '2002-05-14',
        ];
    }
}
