<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }
}
