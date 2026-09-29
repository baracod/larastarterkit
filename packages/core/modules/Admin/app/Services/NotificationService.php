<?php

namespace Modules\Admin\Services;

use Illuminate\Notifications\Notification as BaseNotificationClass;
use Illuminate\Support\Facades\Notification;
use Modules\Admin\Models\NotificationSetting;
use Modules\Auth\Models\User;

class NotificationService
{
    /**
     * Get all notification settings.
     */
    public function getAllSettings()
    {
        return NotificationSetting::all();
    }

    /**
     * Update a notification setting.
     */
    public function updateSetting(string $type, array $channels, bool $enabled)
    {
        return NotificationSetting::updateOrCreate(
            ['notification_type' => $type],
            [
                'channels' => $channels,
                'is_enabled' => $enabled,
            ]
        );
    }

    /**
     * Send a notification to a specific user.
     */
    public function sendToUser(User $user, BaseNotificationClass $notification): void
    {
        $user->notify($notification);
    }

    /**
     * Broadcast a notification to multiple users.
     */
    public function broadcast(mixed $users, BaseNotificationClass $notification): void
    {
        Notification::send($users, $notification);
    }
}
