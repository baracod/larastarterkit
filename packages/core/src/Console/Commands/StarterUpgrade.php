<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Support\StarterLifecycle;
use Illuminate\Console\Command;
use Throwable;

class StarterUpgrade extends Command
{
    protected $signature = 'larastarterkit:upgrade {--dry-run}';

    protected $description = 'Apply pending migrations and repeatable starter upgrades without changing application sources';

    public function handle(StarterLifecycle $lifecycle): int
    {
        if ($this->call('larastarterkit:doctor', ['--json' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run: no changes. After migrations, declared upgrade seeders will run.');

            return self::SUCCESS;
        }
        try {
            $lifecycle->upgrade();
        } catch (Throwable $e) {
            $this->error('Upgrade failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Upgrade complete. Build frontend assets and restart workers during deployment.');

        return self::SUCCESS;
    }
}
