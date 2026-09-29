<?php

namespace Modules\Documentation\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Documentation\Jobs\BuildDocumentation;
use Modules\Documentation\Models\Publication;

class RecoverPublications extends Command
{
    protected $signature = 'documentation:recover';

    protected $description = 'Reprendre les publications orphelines après interruption du worker';

    public function handle(): int
    {
        if (! app(ModuleRegistry::class)->enabled('Documentation')) {
            return self::SUCCESS;
        }
        $root = config('documentation.storage').'/site';
        File::ensureDirectoryExists($root);
        $lock = fopen($root.'/build.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return self::SUCCESS;
        }
        try {
            foreach (Publication::whereIn('status', ['queued', 'building'])->where('updated_at', '<', now()->subMinutes(10))->get() as $publication) {
                // Duplicate deliveries are safe: completed publications are never rebuilt.
                $publication->update(['status' => 'queued']);
                BuildDocumentation::dispatch($publication->id)->onConnection(config('documentation.queue_connection'))->onQueue('documentation');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return self::SUCCESS;
    }
}
