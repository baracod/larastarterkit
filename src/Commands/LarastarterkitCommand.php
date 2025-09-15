<?php

namespace Baracod\Larastarterkit\Commands;

use Illuminate\Console\Command;

class LarastarterkitCommand extends Command
{
    public $signature = 'larastarterkit';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
