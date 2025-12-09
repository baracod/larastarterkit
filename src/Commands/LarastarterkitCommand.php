<?php

namespace Baracod\Larastarterkit\Commands;

use Baracod\Larastarterkit\Generator\Console\ConsoleUI;
use Illuminate\Console\Command;

class LarastarterkitCommand extends Command
{
    // Signature artisan pour exécuter la commande du package.
    protected $signature = 'larastarterkit';

    protected $description = 'La commande principale de Larastarterkit';

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
