<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || config('database.connections.mysql.database') !== 'starter_docker_local') {
    throw new RuntimeException('Local Docker initialization cannot target another environment.');
}

function runLocalCommand(string $command, array $arguments): void
{
    $status = Artisan::call($command, $arguments + ['--no-interaction' => true]);
    echo Artisan::output();
    if ($status !== 0) {
        throw new RuntimeException('Local initialization failed: '.$command);
    }
}

runLocalCommand('migrate', ['--force' => true]);

// Seed only the first time. Existing lots and access settings are preserved.
if (DB::table('auth_roles')->count() === 0) {
    DB::transaction(fn () => runLocalCommand('db:seed', [
        '--class' => Database\Seeders\DatabaseSeeder::class,
        '--force' => true,
    ]));
}

$email = getenv('LOCAL_ADMIN_EMAIL') ?: 'super-admin@starter.local';
if (! User::query()->where('email', $email)->exists()) {
    $password = getenv('LOCAL_ADMIN_PASSWORD');
    if (! is_string($password) || strlen($password) < 8) {
        throw new RuntimeException('LOCAL_ADMIN_PASSWORD must contain at least 8 characters.');
    }
    runLocalCommand('starter:super-admin', [
        '--name' => 'Administrateur Docker local', '--email' => $email, '--password' => $password,
    ]);
}
