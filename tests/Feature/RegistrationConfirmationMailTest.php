<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationConfirmationMailTest extends TestCase
{
    public function test_email_contains_html_and_plain_text_details_without_passwords(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
        $registration = Registration::factory()->make([
            'id' => 42,
            'full_name' => 'Avery Example',
            'email' => 'avery@example.edu',
            'created_at' => now(),
        ]);

        $mail = new RegistrationConfirmation($registration);

        $mail->assertHasSubject('Cloud Computing 2025 — Confirm Your Registration');
        $mail->assertSeeInHtml('Hello Avery Example,');
        $mail->assertSeeInHtml('avery@example.edu');
        $mail->assertSeeInHtml('October 30, 2026');
        $mail->assertSeeInHtml('Cloud Computing 2025');
        $mail->assertSeeInHtml('Confirm Registration');
        $mail->assertSeeInHtml('7 days');
        $mail->assertSeeInHtml($mail->confirmationUrl);
        $mail->assertSeeInText('Hello Avery Example,');
        $mail->assertSeeInText('avery@example.edu');
        $mail->assertSeeInText('October 30, 2026');
        $mail->assertSeeInText($mail->confirmationUrl);
        $mail->assertDontSeeInHtml('test-password');
        $mail->assertDontSeeInText('test-password');
        $mail->assertDontSeeInHtml($registration->password);
        $mail->assertDontSeeInText($registration->password);
        $this->assertStringNotContainsString($registration->password, serialize($mail));
    }

    public function test_link_has_a_valid_seven_day_signature_without_personal_information(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
        $registration = Registration::factory()->make(['id' => 42, 'created_at' => now()]);

        $mail = new RegistrationConfirmation($registration);

        $this->assertTrue(URL::hasValidSignature(Request::create($mail->confirmationUrl)));
        parse_str(parse_url($mail->confirmationUrl, PHP_URL_QUERY), $query);
        $this->assertSame('2026-11-06 12:00:00', Carbon::createFromTimestampUTC($query['expires'])->format('Y-m-d H:i:s'));
        $this->assertStringNotContainsString('/confirm/42?', $mail->confirmationUrl);
        $this->assertStringNotContainsString($registration->email, $mail->confirmationUrl);
        $this->assertStringNotContainsString(rawurlencode($registration->email), $mail->confirmationUrl);
        $this->assertStringNotContainsString($registration->full_name, $mail->confirmationUrl);
    }

    public function test_html_escapes_the_name_while_plain_text_preserves_it(): void
    {
        $name = '<script>alert("name")</script> & Avery';
        $registration = Registration::factory()->make(['id' => 42, 'full_name' => $name, 'created_at' => now()]);

        $mail = new RegistrationConfirmation($registration);

        $mail->assertSeeInHtml($name);
        $mail->assertDontSeeInHtml($name, false);
        $mail->assertSeeInText($name);
    }
}
