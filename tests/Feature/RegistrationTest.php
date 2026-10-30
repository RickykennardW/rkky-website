<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Registration;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_page_renders_the_post_form_with_csrf_protection(): void
    {
        $this->get(route('registration.index'))
            ->assertOk()
            ->assertSee('Create your registration')
            ->assertSee('action="'.route('registration.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('once the backend is connected');
    }

    public function test_valid_registration_is_saved_pending_with_a_hashed_password_and_confirmation_email(): void
    {
        Mail::fake();
        Notification::fake();
        $payload = $this->validPayload([
            'full_name' => '  Avery Example  ',
            'email' => '  Avery@Example.EDU  ',
            'status' => 'verified',
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);

        $response = $this->post(route('registration.store'), $payload);

        $registration = Registration::query()->sole();
        $response->assertRedirectToRoute('registration.success')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('registration_id', $registration->id)
            ->assertSessionMissingInput('password')
            ->assertSessionMissingInput('password_confirmation');
        $this->assertDatabaseHas('registrations', [
            'full_name' => 'Avery Example',
            'email' => 'avery@example.edu',
            'birthdate' => '2002-05-14 00:00:00',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);
        $this->assertTrue(Hash::check($payload['password'], $registration->password));
        $this->assertNotSame($payload['password'], $registration->password);
        $this->assertArrayNotHasKey('password', $registration->toArray());
        Mail::assertSent(RegistrationConfirmation::class, fn (RegistrationConfirmation $mail): bool => $mail->hasTo('avery@example.edu'));
        Mail::assertSentCount(1);
        Notification::assertNothingSent();
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_without_saving(string $field, mixed $value, string $message): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));

        $this->from(route('registration.index'))
            ->post(route('registration.store'), $this->validPayload([$field => $value]))
            ->assertRedirectToRoute('registration.index')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('registrations', 0);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'short name' => ['full_name', 'Al', 'Your full name must be at least 3 characters.'],
            'long name' => ['full_name', str_repeat('a', 151), 'Your full name cannot exceed 150 characters.'],
            'array name' => ['full_name', ['Avery'], 'Enter your full name as text.'],
            'invalid email' => ['email', 'not-an-email', 'Enter a valid email address, such as you@university.edu.'],
            'long email' => ['email', str_repeat('a', 256).'@example.edu', 'Your email address cannot exceed 255 characters.'],
            'array email' => ['email', ['avery@example.edu'], 'Enter a valid email address.'],
            'short password' => ['password', 'short', 'Use at least 8 characters for your password.'],
            'array password' => ['password', ['password'], 'Enter your password as text.'],
            'missing confirmation' => ['password_confirmation', null, 'Re-enter your password.'],
            'array confirmation' => ['password_confirmation', ['password'], 'Re-enter your password as text.'],
            'invalid birthdate' => ['birthdate', 'not-a-date', 'Enter a valid birthdate.'],
            'array birthdate' => ['birthdate', ['2002-05-14'], 'Enter a valid birthdate.'],
            'future birthdate' => ['birthdate', '2030-01-01', 'Your birthdate must be in the past.'],
            'today is not in the past' => ['birthdate', '2026-10-30', 'Your birthdate must be in the past.'],
        ];
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->from(route('registration.index'))
            ->post(route('registration.store'), $this->validPayload(['password_confirmation' => 'different-password']))
            ->assertRedirectToRoute('registration.index')
            ->assertSessionHasErrors(['password' => 'Your passwords do not match.'])
            ->assertSessionMissingInput('password')
            ->assertSessionMissingInput('password_confirmation');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->from(route('registration.index'))->post(route('registration.store'), [])
            ->assertRedirectToRoute('registration.index')
            ->assertSessionHasErrors([
                'full_name' => 'Enter your full name.',
                'email' => 'Enter your student email address.',
                'password' => 'Create a password.',
                'password_confirmation' => 'Re-enter your password.',
                'birthdate' => 'Select your birthdate.',
            ]);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_duplicate_email_is_rejected_case_insensitively_without_overwriting(): void
    {
        $existing = Registration::factory()->create(['email' => 'avery@example.edu']);
        $originalAttributes = $existing->fresh()->getAttributes();

        $this->from(route('registration.index'))
            ->post(route('registration.store'), $this->validPayload(['email' => 'AVERY@EXAMPLE.EDU']))
            ->assertRedirectToRoute('registration.index')
            ->assertSessionHasErrors(['email' => 'This email address is already registered for Cloud Computing 2025.']);

        $this->assertDatabaseCount('registrations', 1);
        $this->assertSame($originalAttributes, $existing->fresh()->getAttributes());
    }

    public function test_database_uniqueness_handles_a_concurrent_registration_with_a_friendly_error(): void
    {
        Registration::creating(function (Registration $registration): void {
            Registration::factory()->createQuietly([
                'email' => strtoupper($registration->email),
                'full_name' => 'Earlier Student',
            ]);
        });

        $this->from(route('registration.index'))
            ->post(route('registration.store'), $this->validPayload())
            ->assertRedirectToRoute('registration.index')
            ->assertSessionHasErrors(['email' => 'This email address is already registered for Cloud Computing 2025.']);

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseHas('registrations', ['full_name' => 'Earlier Student']);
    }

    public function test_validation_flashes_safe_input_without_passwords(): void
    {
        $name = '<script>alert("name")</script>';
        $payload = $this->validPayload(['full_name' => $name, 'email' => 'invalid-email']);
        $this->from(route('registration.index'))->post(route('registration.store'), $payload)
            ->assertSessionHasErrors(['email' => 'Enter a valid email address, such as you@university.edu.'])
            ->assertSessionHasInput('full_name', $name)
            ->assertSessionHasInput('email', 'invalid-email')
            ->assertSessionHasInput('birthdate', '2002-05-14')
            ->assertSessionMissingInput('password')
            ->assertSessionMissingInput('password_confirmation');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_redirected_validation_errors_are_accessible_and_escape_restored_input(): void
    {
        $name = '<script>alert("name")</script>';
        $payload = $this->validPayload(['full_name' => $name, 'email' => 'invalid-email']);

        $this->followingRedirects()->from(route('registration.index'))
            ->post(route('registration.store'), $payload)
            ->assertOk()
            ->assertSee($name)
            ->assertDontSee($name, false)
            ->assertSee('value="invalid-email"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('Enter a valid email address, such as you@university.edu.')
            ->assertDontSee($payload['password']);
    }

    public function test_non_string_old_input_does_not_break_the_form(): void
    {
        $this->withSession(['_old_input' => ['full_name' => ['invalid'], 'email' => ['invalid'], 'birthdate' => ['invalid']]])
            ->get(route('registration.index'))
            ->assertOk();
    }

    public function test_success_page_uses_only_the_current_session_and_escapes_the_name(): void
    {
        $name = '<script>alert("name")</script>';
        $registration = Registration::factory()->create(['full_name' => $name]);
        $other = Registration::factory()->create(['full_name' => 'Another Student']);

        $response = $this->withSession(['registration_id' => $registration->id])
            ->get(route('registration.success', ['registration_id' => $other->id]));

        $response->assertOk()
            ->assertSee('Registration received.')
            ->assertSee($name)
            ->assertDontSee($name, false)
            ->assertSee('Pending email verification')
            ->assertDontSee($other->full_name)
            ->assertDontSee($registration->email)
            ->assertDontSee($registration->password);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_direct_success_access_cannot_select_a_student_from_the_url(): void
    {
        $registration = Registration::factory()->create();

        $this->get(route('registration.success', ['registration_id' => $registration->id]))
            ->assertRedirectToRoute('registration.index')
            ->assertDontSee($registration->full_name);
    }

    public function test_success_page_redirects_if_the_session_record_no_longer_exists(): void
    {
        $this->withSession(['registration_id' => 999])->get(route('registration.success'))
            ->assertRedirectToRoute('registration.index');
    }

    public function test_repeated_attempts_for_the_same_email_are_rate_limited(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('registration.store'), [])->assertRedirect();
        }

        $this->post(route('registration.store'), [])->assertTooManyRequests();

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_changing_email_addresses_cannot_bypass_the_ip_rate_limit(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post(route('registration.store'), ['email' => "student{$attempt}@example.edu"])->assertRedirect();
        }

        $this->post(route('registration.store'), ['email' => 'another@example.edu'])->assertTooManyRequests();

        $this->assertDatabaseCount('registrations', 0);
    }

    #[TestWith([null])]
    #[TestWith(['incorrect-token'])]
    public function test_registration_without_a_valid_csrf_token_is_rejected(?string $token): void
    {
        $this->enforceCsrfProtection();

        $this->withSession(['_token' => str_repeat('a', 40)])
            ->post(route('registration.store'), $this->validPayload(['_token' => $token]))
            ->assertStatus(419);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_registration_with_a_matching_csrf_token_is_accepted(): void
    {
        Mail::fake();
        $this->enforceCsrfProtection();
        $token = str_repeat('a', 40);

        $this->withSession(['_token' => $token])
            ->post(route('registration.store'), $this->validPayload(['_token' => $token]))
            ->assertRedirectToRoute('registration.success');

        $this->assertDatabaseCount('registrations', 1);
        Mail::assertSentCount(1);
    }

    private function enforceCsrfProtection(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Avery Example',
            'email' => 'avery@example.edu',
            'password' => 'test-password-2025',
            'password_confirmation' => 'test-password-2025',
            'birthdate' => '2002-05-14',
        ], $overrides);
    }
}
