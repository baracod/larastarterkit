<?php

namespace Baracod\Larastarterkit\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** A harmless probe proving that the configured Redis queue is actually consumed. */
class QueueProbe implements ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    public int $tries = 1;

    public function __construct(public readonly string $probeId)
    {
        $this->onConnection('redis');
        $this->onQueue((string) config('queue.connections.redis.queue', 'default'));
    }

    public function handle(): void
    {
        Cache::store('redis')->put('starter:queue-probe:'.$this->probeId, now()->utc()->toIso8601String(), 120);
    }
}
