<?php

namespace Baracod\Larastarterkit\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormLocationReferencesChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $reference) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('form-location-references'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'form-location-references.changed';
    }

    /**
     * @return array{reference: string, changed_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'reference' => $this->reference,
            'changed_at' => now()->toISOString(),
        ];
    }
}
