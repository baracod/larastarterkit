<?php

namespace Baracod\Larastarterkit\Commands;

use Baracod\Larastarterkit\Generator\Console\ConsoleUI;
use Illuminate\Console\Command;

class LarastarterkitCommand extends Command
{
    public $signature = 'larastarterkit';

    public $description = 'My command';

    public function handle(): int
    {
        try {
            $generatorUiConsole = ConsoleUI::for();
            $generatorUiConsole->interactive();

            return self::SUCCESS;
        } catch (\Throwable $th) {
            $this->error('Error: '.$th->getMessage());

            return self::FAILURE;
        }
    }
}
