<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Services\DemoWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('campusspace.demo')) {
            config([
                'database.default' => 'campusspace_demo',
                'database.connections' => [],
                'campusspace.legacy_auth' => [],
                'auth.defaults.guard' => 'demo',
                'auth.guards.demo' => ['driver' => 'campusspace-demo'],
                'session.driver' => 'file',
                'session.cookie' => 'campusspace_demo_session',
                'cache.default' => 'file',
            ]);
        }
    }

    public function boot(): void
    {
        if (config('campusspace.demo')) {
            Auth::viaRequest('campusspace-demo', function (Request $request): ?User {
                $role = $request->hasSession() ? $request->session()->get('campusspace_demo.role') : null;

                return in_array($role, ['student', 'admin'], true) ? DemoWorkspace::user($role) : null;
            });
        }

        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }
}
