<?php

use App\Models\User;
use App\Services\ProductionReadiness;
use Illuminate\Support\Facades\Artisan;

Artisan::command('campusspace:check-production {--schema : Check migrated tables and built assets} {--mode=normal : Expected application mode}', function (ProductionReadiness $readiness): int {
    if ($this->option('mode') !== (config('campusspace.demo') ? 'demo' : 'normal')) {
        $this->error('The active application mode does not match the requested deployment.');

        return 1;
    }
    $issues = $readiness->issues((bool) $this->option('schema'));

    foreach ($issues as $issue) {
        $this->error($issue);
    }

    if ($issues !== []) {
        return 1;
    }

    $this->info('CampusSpace production checks passed.');

    return 0;
})->purpose('Verify production settings and PostgreSQL without displaying credentials');

Artisan::command('campusspace:make-admin {email}', function (): int {
    if (config('campusspace.demo')) {
        $this->error('Account administration requires Normal Mode.');

        return 1;
    }

    $email = mb_strtolower(trim($this->argument('email')));
    $user = User::query()->where('email', $email)->first();

    if (! $user) {
        $this->error('The account must sign in or register in CampusSpace first.');

        return 1;
    }

    $user->profile()->update(['role' => 'admin']);
    $this->info('CampusSpace administrator access granted.');

    return 0;
})->purpose('Grant administrator access to an existing CampusSpace account');
