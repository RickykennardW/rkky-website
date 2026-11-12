<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app['config'];
        $database = $config->get('database.connections.pgsql');

        if (! $app->environment('testing') || $config->get('database.default') !== 'pgsql'
            || $database['host'] !== '127.0.0.1' || $database['database'] !== 'campusspace_test'
            || ! empty($database['url'])) {
            throw new RuntimeException('Tests require an isolated PostgreSQL database named campusspace_test on loopback.');
        }

        if (! self::$migrated) {
            $app['db']->unprepared(file_get_contents(database_path('testing/auth.sql')));
            $kernel = $app->make(Kernel::class);
            if ($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('The local test migrations failed.');
            }
            self::$migrated = true;
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
