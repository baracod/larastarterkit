<?php

namespace Modules\Auth\Notifications;

use Modules\Admin\Notifications\BaseNotification;

/**
 * Base class for all Auth-related notifications.
 *
 * Inherits unified channel handling from BaseNotification.
 * Supports mail, database, and broadcast channels.
 */
abstract class AuthNotification extends BaseNotification
{
    /**
     * Default channels for Auth notifications: mail first, then database if configured.
     */
    public function via(object $notifiable): array
    {
        // Try to get settings from database
        $setting = \Modules\Admin\Models\NotificationSetting::where('notification_type', $this->notificationType)->first();

        if ($setting && $setting->is_enabled) {
            $channels = parent::via($notifiable);
            if (! empty($channels)) {
                return $channels;
            }
        }

        // Fallback: send via mail only for Auth notifications if no specific setting exists
        return ['mail'];
    }
}
