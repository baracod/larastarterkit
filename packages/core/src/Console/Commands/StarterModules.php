<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;

class StarterModules extends Command
{
    protected $signature = 'larastarterkit:modules {--json}';

    protected $description = 'Export installed module metadata for frontend tooling';

    public function handle(ModuleRegistry $registry): int
    {
        $this->line(json_encode(['modules' => $registry->all(), 'statuses' => $registry->statuses()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
