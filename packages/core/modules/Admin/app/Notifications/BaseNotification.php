<?php

namespace Modules\Admin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Modules\Admin\Models\NotificationSetting;
use Modules\Admin\Notifications\Channels\AdminModuleDatabaseChannel;

/**
 * Base class for all system notifications (Admin, Auth, etc.)
 *
 * Provides unified channel handling, broadcasting, and database persistence.
 * Subclasses must implement: toDatabase() and optionally toMail()
 */
abstract class BaseNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public ?\Modules\Auth\Models\User $sender;

    protected string $notificationType;

    public function __construct()
    {
        $this->sender = auth()->user();
        // Automatically determine notification type from class name
        $this->notificationType = static::class;
    }

    /**
     * Get notification channels based on settings.
     *
     * Falls back to ['mail'] for Auth notifications, ['database', 'broadcast'] for Admin
     */
    public function via(object $notifiable): array
    {
        $setting = NotificationSetting::where('notification_type', $this->notificationType)->first();

        if (! $setting || ! $setting->is_enabled) {
            return [];
        }

        $channels = $setting->channels ?? [];

        return array_map(function ($channel) {
            return match ($channel) {
                'database' => AdminModuleDatabaseChannel::class,
                'mail' => 'mail',
                'push' => 'broadcast',
                'broadcast' => 'broadcast',
                default => $channel,
            };
        }, $channels);
    }

    /**
     * Get broadcast data with sender info.
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);
        $data['sender'] = $this->sender ? [
            'id' => $this->sender->id,
            'name' => $this->sender->name,
            'avatar' => $this->sender->avatar ?? null,
        ] : null;

        return new BroadcastMessage([
            'notification' => $data,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }

    /**
     * Subclasses MUST implement this method to define database notification data.
     */
    abstract public function toDatabase($notifiable): array;

    /**
     * Helper to build premium styled emails.
     */
    protected function buildPremiumMail(string $title, string $message, string $actionText = 'Voir', ?string $actionUrl = null): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($title)
            ->view('admin::emails.notification', [
                'title' => $title,
                'messageText' => $message,
                'actionText' => $actionText,
                'actionUrl' => $actionUrl ?: url('/'),
                'sender' => $this->sender ? [
                    'name' => $this->sender->name,
                    'avatar' => $this->sender->avatar,
                ] : null,
            ]);
    }
}
