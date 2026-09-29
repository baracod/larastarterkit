<?php

namespace Baracod\Larastarterkit\Core\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class DashboardUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.updated';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
