<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadiness
{
    public function issues(bool $withSchema): array
    {
        $issues = [];

        if (! app()->environment('production') || config('app.debug') || rtrim(config('app.url'), '/') !== 'https://rkky.my.id') {
            $issues[] = 'Set APP_ENV=production, APP_DEBUG=false and APP_URL=https://rkky.my.id.';
        }

        if (! config('app.key')) {
            $issues[] = 'Set a Laravel APP_KEY.';
        }

        if (! config('session.secure')) {
            $issues[] = 'Set SESSION_SECURE_COOKIE=true.';
        }

        if (config('database.default') !== 'pgsql') {
            $issues[] = 'Set DB_CONNECTION=pgsql.';

            return $issues;
        }

        try {
            $connection = DB::connection();
            if (! in_array($connection->getConfig('sslmode'), ['require', 'verify-ca', 'verify-full'], true)) {
                $issues[] = 'Require TLS for the PostgreSQL connection with DB_SSLMODE=require or stronger.';
            } else {
                $connection->selectOne('select 1');
            }
        } catch (Throwable) {
            $issues[] = 'PostgreSQL connection failed. Check the Supabase Session pooler or direct credentials and PHP pdo_pgsql.';
        }

        if ($issues === [] && $withSchema && ! $this->applicationReady()) {
            $issues[] = 'The CampusSpace schema or Vite production assets are missing.';
        }

        return $issues;
    }

    public function applicationReady(): bool
    {
        try {
            return $this->assetsReady()
                && Schema::hasTable('public.rooms')
                && Schema::hasTable('public.bookings')
                && Schema::hasTable('campus_private.users')
                && Schema::hasTable('campus_private.notifications');
        } catch (Throwable) {
            return false;
        }
    }

    private function assetsReady(): bool
    {
        $manifestPath = public_path('build/manifest.json');
        if (is_file(public_path('hot')) || ! is_readable($manifestPath)) {
            return false;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            $file = $manifest[$entry]['file'] ?? null;
            if (! is_string($file) || ! is_file(public_path('build/'.$file))) {
                return false;
            }
        }

        return true;
    }
}
