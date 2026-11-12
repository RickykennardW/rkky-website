<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_home_and_auth_forms_respond_and_workspace_requires_login(): void
    {
        $this->get('/')->assertOk()->assertSee('data-app="campusspace"', false)->assertSee('Book a campus room');
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/register')->assertOk()->assertSee('Create your account');
        $this->get('/rooms')->assertRedirect(route('login'));
        $this->getJson('/api/availability')->assertUnauthorized();
    }

    public function test_registration_hashes_the_password_and_cannot_choose_an_admin_role(): void
    {
        $password = 'strong-campus-password';
        $this->post('/register', [
            'full_name' => 'Riley Student', 'email' => 'RILEY@example.test',
            'password' => $password, 'password_confirmation' => $password, 'role' => 'admin',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'riley@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('student', $user->profile->role);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
        $this->assertDatabaseMissing('auth.users', ['id' => $user->id]);
        $this->assertFalse(DB::scalar("select has_table_privilege('authenticated', 'campus_private.users', 'SELECT')"));
        $this->assertFalse(DB::scalar("select has_table_privilege('service_role', 'campus_private.users', 'SELECT')"));
        $this->assertTrue(DB::scalar("select relrowsecurity from pg_catalog.pg_class where oid = 'campus_private.users'::regclass"));

        $this->get('/admin')->assertForbidden();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_validates_credentials_and_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'campus-test-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_legacy_account_is_linked_only_after_supabase_verifies_the_matching_identity(): void
    {
        $id = (string) Str::uuid();
        $email = 'legacy@example.test';
        DB::table('auth.users')->insert(['id' => $id, 'email' => $email, 'raw_user_meta_data' => '{"full_name":"Legacy Student"}']);
        config([
            'campusspace.legacy_auth.url' => 'https://supabase.example.test',
            'campusspace.legacy_auth.publishable_key' => 'sb_publishable_test',
        ]);

        $password = 'verified-campus-password';
        $this->from('/register')->post('/register', [
            'full_name' => 'Duplicate', 'email' => $email, 'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors('email');

        Http::preventStrayRequests();
        Http::fakeSequence('https://supabase.example.test/auth/v1/token*')
            ->push(['user' => ['id' => (string) Str::uuid(), 'email' => $email, 'email_confirmed_at' => now()->toIso8601String()]])
            ->push(['user' => ['id' => $id, 'email' => $email, 'email_confirmed_at' => now()->toIso8601String()]]);

        $this->from('/login')->post('/login', ['email' => $email, 'password' => $password])->assertSessionHasErrors('email');
        $this->assertNull(User::query()->find($id));
        $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect(route('dashboard'));

        $linked = User::query()->findOrFail($id);
        $this->assertAuthenticatedAs($linked);
        $this->assertSame('Legacy Student', $linked->profile->full_name);
        $this->assertTrue(Hash::check($password, $linked->password));
        $this->assertDatabaseCount('profiles', 1);
    }

    public function test_email_verification_can_be_enabled_for_native_accounts(): void
    {
        config(['campusspace.verify_email' => true]);
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get('/rooms')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk();
        $this->post('/email/verification-notification')->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]))->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
