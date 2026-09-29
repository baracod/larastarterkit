<?php

namespace Tests\Feature;

use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Modules\Admin\Notifications\BaseNotification;
use Modules\Auth\Models\User;
use Tests\TestCase;

class NotificationBroadcastContractTest extends TestCase
{
    public function test_system_notifications_match_the_authorized_frontend_channel_and_event(): void
    {
        $user = User::factory()->create();
        Event::fake([BroadcastNotificationCreated::class]);
        $notification = new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                return ['broadcast'];
            }

            public function toDatabase($notifiable): array
            {
                return ['type' => 'info', 'title' => 'System event', 'message' => 'Test'];
            }
        };
        $user->notifyNow($notification);

        Event::assertDispatched(BroadcastNotificationCreated::class, function ($event) use ($user) {
            $this->assertSame('private-user.'.$user->id, $event->broadcastOn()[0]->name);
            $this->assertSame('notification.created', $event->broadcastAs());
            $this->assertSame('System event', $event->broadcastWith()['notification']['title']);
            $this->assertArrayNotHasKey('id', $event->broadcastWith()['notification']);

            return true;
        });
    }
}
