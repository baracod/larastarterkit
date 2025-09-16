<?php

namespace Baracod\Larastarterkit\Commands;

use Illuminate\Console\Command;
use Baracod\Larastarterkit\Generator\Backend\Model\ModelDefinitionManager;

class LarastarterkitCommand extends Command
{
    public $signature = 'larastarterkit';

    public $description = 'My command';

    public function handle(): int
    {
        $mgr = new ModelDefinitionManager("Blog");
        $state = $mgr->interactive();
        $this->comment('All done');

        return self::SUCCESS;
    }
}
