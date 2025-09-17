<?php

namespace Baracod\Larastarterkit\Commands;

use Illuminate\Console\Command;
use Baracod\Larastarterkit\Generator\Model\ModelDefinitionManager;

class LarastarterkitCommand extends Command
{
    public $signature = 'larastarterkit';

    public $description = 'My command';

    public function handle(): int
    {
        try {
            $mgr = new ModelDefinitionManager('Blog');
            $mgr->interactive();
            $this->comment('All done');

            return self::SUCCESS;
        } catch (\Throwable $th) {
            $this->error('Error: ' . $th->getMessage());
            return self::FAILURE;
        }
    }
}
