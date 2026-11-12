<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Accounts
{
    public function register(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $this->lockEmail($email);

            if ($this->legacyProfileExists($email) || User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already has a CampusSpace profile. Sign in with your existing account.']);
            }

            $profile = Profile::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name, 'email' => $email]);

            return User::query()->create(['id' => $profile->id, 'email' => $email, 'password' => Hash::make($password)]);
        });
    }

    public function linkLegacyAccount(string $email, string $password): ?User
    {
        $url = config('campusspace.legacy_auth.url');
        $key = config('campusspace.legacy_auth.publishable_key');

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! is_string($key) || ! str_starts_with($key, 'sb_publishable_')
            || strlen($password) > 72
            || User::query()->where('email', $email)->exists()
            || ! $this->legacyProfileExists($email)) {
            return null;
        }

        try {
            $response = Http::acceptJson()->withHeaders(['apikey' => $key])->withoutRedirecting()
                ->connectTimeout(3)->timeout(8)
                ->post(rtrim($url, '/').'/auth/v1/token?grant_type=password', [
                    'email' => $email,
                    'password' => $password,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $verifiedId = $response->json('user.id');
        $verifiedEmail = $response->json('user.email');

        if (! is_string($verifiedId) || ! Str::isUuid($verifiedId)
            || ! is_string($verifiedEmail) || mb_strtolower($verifiedEmail) !== $email
            || ! $response->json('user.email_confirmed_at')) {
            return null;
        }

        return DB::transaction(function () use ($email, $password, $verifiedId): ?User {
            $this->lockEmail($email);
            $profile = Profile::query()->whereKey($verifiedId)->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();

            if (! $profile || User::query()->where('email', $email)->orWhereKey($verifiedId)->exists()) {
                return null;
            }

            return User::query()->create([
                'id' => $profile->id,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        });
    }

    private function legacyProfileExists(string $email): bool
    {
        return Profile::query()->whereRaw('lower(email) = ?', [$email])->exists();
    }

    private function lockEmail(string $email): void
    {
        DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$email]);
    }
}
