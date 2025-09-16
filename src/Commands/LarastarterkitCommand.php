<?php

namespace Baracod\Larastarterkit\Commands;

use Baracod\Larastarterkit\Generator\Backend\Model\ModelDefinitionManager;
use Illuminate\Console\Command;

class LarastarterkitCommand extends Command
{
    public $signature = 'larastarterkit';

    public $description = 'My command';

    public function handle(): int
    {
        $mgr = new ModelDefinitionManager('Blog');
        $state = $mgr->interactive();
        $this->comment('All done');

        return self::SUCCESS;
    }
}
