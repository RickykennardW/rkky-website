<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
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

        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }
        if (! is_string($key) || ! Encrypter::supported($key, config('app.cipher'))) {
            $issues[] = 'Set a valid production APP_KEY for the Laravel cipher.';
        }

        if (! config('session.secure')) {
            $issues[] = 'Set SESSION_SECURE_COOKIE=true.';
        }

        $writable = [
            'storage/app' => storage_path('app'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => app()->bootstrapPath('cache'),
        ];
        if (config('session.driver') === 'file') {
            $writable['file session directory'] = config('session.files');
        }
        if (config('cache.default') === 'file') {
            $writable['file cache directory'] = config('cache.stores.file.path');
        }
        foreach ($writable as $label => $directory) {
            if (! is_string($directory) || ! is_dir($directory) || ! is_writable($directory)) {
                $issues[] = "Make {$label} writable by the site user.";
            }
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
                $issues = array_merge($issues, $this->databaseDriverIssues());
            }
        } catch (Throwable) {
            $issues[] = 'PostgreSQL connection failed. Check the Supabase Session pooler or direct credentials and PHP pdo_pgsql.';
        }

        if ($issues === [] && $withSchema) {
            if (! $this->applicationReady()) {
                $issues[] = 'The CampusSpace schema or Vite production assets are missing.';
            }
            try {
                $migrator = app('migrator');
                if (! $migrator->repositoryExists() || array_diff(
                    array_keys($migrator->getMigrationFiles(database_path('migrations'))),
                    $migrator->getRepository()->getRan(),
                ) !== []) {
                    $issues[] = 'CampusSpace migrations are pending. Check php artisan migrate:status.';
                }
            } catch (Throwable) {
                $issues[] = 'The migration ledger could not be verified.';
            }
        }

        return $issues;
    }

    private function databaseDriverIssues(): array
    {
        $issues = [];
        if (config('session.driver') === 'database'
            && ! Schema::connection(config('session.connection'))->hasTable(config('session.table'))) {
            $issues[] = 'The database session table is missing. Use SESSION_DRIVER=file or provide the configured table.';
        }
        if (config('cache.default') === 'database') {
            $schema = Schema::connection(config('cache.stores.database.connection'));
            $lockSchema = Schema::connection(config('cache.stores.database.lock_connection') ?: config('cache.stores.database.connection'));
            if (! $schema->hasTable(config('cache.stores.database.table'))
                || ! $lockSchema->hasTable(config('cache.stores.database.lock_table') ?? 'cache_locks')) {
                $issues[] = 'The database cache tables are missing. Use CACHE_STORE=file or provide the configured tables.';
            }
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
