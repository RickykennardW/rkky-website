<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Encryption\Encrypter;

final class ProvisioningException extends RuntimeException {}

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    if (($argv[1] ?? '') === '--server') {
        provisionOnServer();
    } else {
        provisionOverSsh($argv[1] ?? '');
    }
} catch (ProvisioningException $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Production provisioning failed. Check the private environment and SSH configuration.'.PHP_EOL);
    exit(1);
}

function provisionOverSsh(string $identity): void
{
    $identity = realpath($identity);
    if ($identity === false || ! is_file($identity)) {
        throw new ProvisioningException('Supply the path to the existing SSH private identity.');
    }

    chdir(dirname(__DIR__, 2));
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $connection = $app['db']->connection();
    $host = $connection->getConfig('host');
    $username = $connection->getConfig('username');
    $sslmode = $connection->getConfig('sslmode');
    if ($connection->getDriverName() !== 'pgsql' || ! is_string($host)
        || ! str_ends_with($host, '.pooler.supabase.com') || (int) $connection->getConfig('port') !== 5432
        || ! in_array($sslmode, ['require', 'verify-ca', 'verify-full'], true)
        || ! is_string($username) || ! preg_match('/^[a-z_][a-z0-9_]*\.([a-z0-9]{20})$/D', $username, $project)
        || ! $connection->getConfig('database') || ! $connection->getConfig('password')) {
        throw new ProvisioningException('Configure the existing Supabase Session Pooler and TLS in the local environment.');
    }

    $apiUrl = 'https://'.$project[1].'.supabase.co';
    $publishable = config('campusspace.legacy_auth.publishable_key');
    if (rtrim((string) config('campusspace.legacy_auth.url'), '/') !== $apiUrl
        || ! is_string($publishable) || ! preg_match('/^sb_publishable_[A-Za-z0-9_-]+$/D', $publishable)) {
        throw new ProvisioningException('Set the existing project SUPABASE_URL and SUPABASE_PUBLISHABLE_KEY in the local environment.');
    }

    $pdo = $connection->getPdo();
    $pdo->beginTransaction();
    try {
        $pdo->exec('set transaction read only');
        $pdo->exec("set local statement_timeout = '6s'");
        $migrator = $app['migrator'];
        if (! $migrator->repositoryExists() || array_diff(
            array_keys($migrator->getMigrationFiles(database_path('migrations'))),
            $migrator->getRepository()->getRan(),
        ) !== []) {
            throw new ProvisioningException('Verify that the CampusSpace migrations have run in the selected Supabase database.');
        }
    } finally {
        $pdo->rollBack();
    }

    $databaseUrl = sprintf('postgresql://%s:%s@%s:5432/%s?sslmode=%s',
        rawurlencode($username), rawurlencode($connection->getConfig('password')), $host,
        rawurlencode($connection->getConfig('database')), $sslmode,
    );
    $payload = json_encode(['DB_URL' => $databaseUrl, 'SUPABASE_URL' => $apiUrl, 'SUPABASE_PUBLISHABLE_KEY' => $publishable], JSON_THROW_ON_ERROR);

    // The remote command carries only this script. Credentials travel in the verified SSH stdin stream.
    $source = base64_encode(substr(file_get_contents(__FILE__), 5));
    $remote = 'test "$(id -un)" = rkkyweb && cd "$HOME/htdocs/rkky.my.id"'
        .' && test "$(git symbolic-ref --short HEAD)" = main && test -z "$(git ls-files -- .env)"'
        .' && { exec 9>"$(git rev-parse --git-path campusspace-deploy.lock)"; flock -n 9; }'
        .' && php -r \'eval(base64_decode("'.$source.'"));\' -- --server';

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $process = proc_open([
            'ssh', '-4', '-T', '-i', $identity, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes', '-o', 'UpdateHostKeys=no', '-o', 'PasswordAuthentication=no',
            '-o', 'ConnectTimeout=10', '-o', 'ServerAliveInterval=10', '-o', 'ServerAliveCountMax=2',
            'rkkyweb@rkky.my.id', $remote,
        ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, options: ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new ProvisioningException('Could not start the verified SSH client.');
        }
        @fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $result = trim(stream_get_contents($pipes[1]));
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status === 0 && in_array($result, ['CAMPUSSPACE_ENV_UPDATED', 'CAMPUSSPACE_ENV_CURRENT'], true)) {
            echo $result === 'CAMPUSSPACE_ENV_UPDATED' ? 'Private server environment updated.' : 'Private server environment already configured.';
            echo PHP_EOL;

            return;
        }
        if ($status !== 255) {
            break;
        }
    }

    throw new ProvisioningException('The server did not confirm provisioning. Check SSH, APP_KEY, .env ownership, and any existing database settings.');
}

function provisionOnServer(): void
{
    if (basename(getcwd()) !== 'rkky.my.id' || basename(dirname(getcwd())) !== 'htdocs') {
        throw new ProvisioningException('Provisioning requires the existing server site directory.');
    }
    require 'vendor/autoload.php';
    if (! is_file('.env') || is_link('.env') || ! is_writable('.env')) {
        throw new ProvisioningException('The existing server .env must be a writable private file.');
    }

    $received = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $original = file_get_contents('.env');
    $env = Dotenv::parse($original);
    $key = $env['APP_KEY'] ?? '';
    $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    if (! is_string($decoded) || ! Encrypter::supported($decoded, 'AES-256-CBC')) {
        throw new ProvisioningException('The existing server APP_KEY is invalid for the Laravel cipher.');
    }

    $settings = [
        'APP_NAME' => 'CampusSpace', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
        'APP_URL' => 'https://rkky.my.id', 'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync',
    ];
    $hasSplitCredentials = ! empty($env['DB_HOST']) && ! empty($env['DB_USERNAME']) && ! empty($env['DB_PASSWORD']);
    $hasPostgres = ($env['DB_CONNECTION'] ?? '') === 'pgsql' && (! empty($env['DB_URL']) || $hasSplitCredentials);
    if (! $hasPostgres) {
        $host = $env['DB_HOST'] ?? '';
        $hasSupabase = $hasSplitCredentials && (str_ends_with($host, '.pooler.supabase.com') || str_ends_with($host, '.supabase.co'));
        if (! empty($env['DB_URL']) || $hasSupabase) {
            throw new ProvisioningException('Review the existing database credentials before changing the server database driver.');
        }
        $settings += ['DB_CONNECTION' => 'pgsql', 'DB_URL' => $received['DB_URL'], 'DB_SSLMODE' => 'require'];
    }

    if (filter_var($env['SUPABASE_URL'] ?? '', FILTER_VALIDATE_URL) && rtrim($env['SUPABASE_URL'], '/') !== $received['SUPABASE_URL']) {
        throw new ProvisioningException('The server already has a different Supabase API project.');
    }
    $settings += ['SUPABASE_URL' => $received['SUPABASE_URL']];
    if (empty($env['SUPABASE_PUBLISHABLE_KEY'])) {
        $settings += ['SUPABASE_PUBLISHABLE_KEY' => $received['SUPABASE_PUBLISHABLE_KEY']];
    }

    $updated = $original;
    foreach ($settings as $name => $value) {
        if (! is_string($value) || ! preg_match('~^[A-Za-z0-9._:/@%?=&+\-]+$~D', $value)) {
            throw new ProvisioningException('An environment value could not be encoded safely.');
        }
        if (($env[$name] ?? null) === $value) {
            continue;
        }
        $assignment = $name.'='.$value;
        $updated = preg_replace_callback('/^[ \t]*(?:export[ \t]+)?'.$name.'[ \t]*=[^\r\n]*/m', fn () => $assignment, $updated, -1, $count);
        if ($count > 1) {
            throw new ProvisioningException('Resolve duplicate server environment assignments before provisioning.');
        }
        if ($count === 0) {
            $updated = rtrim($updated, "\r\n").PHP_EOL.$assignment.PHP_EOL;
        }
    }

    $candidate = Dotenv::parse($updated);
    foreach ($settings + ['APP_KEY' => $key] as $name => $value) {
        if (($candidate[$name] ?? null) !== $value) {
            throw new ProvisioningException('The candidate server environment did not match the intended settings.');
        }
    }
    if ($updated === $original) {
        echo 'CAMPUSSPACE_ENV_CURRENT'.PHP_EOL;

        return;
    }

    $temporary = tempnam(getcwd(), '.env.campusspace-');
    if ($temporary === false || dirname($temporary) !== getcwd()) {
        throw new ProvisioningException('Could not create a private file beside the server environment.');
    }
    try {
        if (! chmod($temporary, 0600) || file_put_contents($temporary, $updated, LOCK_EX) !== strlen($updated)
            || (filegroup($temporary) !== filegroup('.env') && ! chgrp($temporary, filegroup('.env')))
            || ! chmod($temporary, fileperms('.env') & 0660) || file_get_contents('.env') !== $original
            || ! rename($temporary, '.env')) {
            throw new ProvisioningException('The server environment could not be updated atomically.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }

    echo 'CAMPUSSPACE_ENV_UPDATED'.PHP_EOL;
}
