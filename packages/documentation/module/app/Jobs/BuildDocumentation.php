<?php

namespace Modules\Documentation\Jobs;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Documentation\Models\Publication;
use Modules\Documentation\Services\PortalBuilder;
use Throwable;

class BuildDocumentation implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public int $publicationId) {}

    public function handle(PortalBuilder $builder): void
    {
        $publication = Publication::findOrFail($this->publicationId);
        if (in_array($publication->status, ['succeeded', 'failed'])) {
            return;
        }
        if (! app(ModuleRegistry::class)->enabled('Documentation')) {
            $this->failed(new \RuntimeException('Le module Documentation est désactivé.'));

            return;
        }
        // All requests, including rollbacks, share the same ordered queue.
        if (Publication::where('id', '<', $publication->id)->whereIn('status', ['queued', 'building'])->exists()) {
            $this->release(10);

            return;
        }
        if (! $builder->build($publication)) {
            $this->release(10);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Publication::whereKey($this->publicationId)->whereNotIn('status', ['succeeded', 'failed'])->update([
            'status' => 'failed', 'log' => mb_substr($exception?->getMessage() ?? 'Construction interrompue.', 0, 10000), 'finished_at' => now(),
        ]);
    }
}
