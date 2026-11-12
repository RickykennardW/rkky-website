<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'id' => fn (array $attributes): string => Profile::query()->create([
                'id' => (string) Str::uuid(),
                'email' => $attributes['email'],
                'full_name' => fake()->name(),
            ])->id,
            'password' => Hash::make('campus-test-password'),
            'email_verified_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->profile()->update(['role' => 'admin']));
    }
}
