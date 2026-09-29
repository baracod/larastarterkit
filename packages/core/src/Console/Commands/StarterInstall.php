<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Illuminate\Console\Command;

class StarterInstall extends Command
{
    protected $signature = 'larastarterkit:install';

    protected $description = 'Initialize a configured application without creating predefined user credentials';

    public function handle(): int
    {
        $result = $this->call('larastarterkit:upgrade', ['--no-interaction' => true]);
        if ($result === self::SUCCESS) {
            $this->info('Create your administrator with auth:super-admin:create.');
        }

        return $result;
    }
}
