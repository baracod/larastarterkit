<?php

namespace Modules\Admin\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Modules\Admin\Models\Notification as AdminNotification;

class AdminModuleDatabaseChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        $data = $notification->toDatabase($notifiable);
        $payloadData = $data['data'] ?? [];

        // Check if notification has sender property (from BaseNotification)
        if (isset($notification->sender) && $notification->sender) {
            $payloadData['sender'] = [
                'id' => $notification->sender->id,
                'name' => $notification->sender->name,
                'avatar' => $notification->sender->avatar ?? null,
            ];
        }

        AdminNotification::create([
            'user_id' => $notifiable->id,
            'type' => $data['type'] ?? 'info',
            'title' => $data['title'],
            'message' => $data['message'] ?? null,
            'data' => $payloadData,
            'read_at' => null,
        ]);
    }
}
