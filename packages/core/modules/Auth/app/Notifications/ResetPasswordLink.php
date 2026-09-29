<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset notification for Auth users.
 *
 * Delivered through the dedicated Horizon mail queue.
 */
class ResetPasswordLink extends QueuedAuthMail
{
    protected string $resetToken;

    protected string $userLocale;

    public function __construct(string $token, ?string $locale = null)
    {
        parent::__construct($locale);
        $this->resetToken = $token;
        $this->userLocale = $this->locale;
    }

    /**
     * Mail channel - sends password reset link via email.
     */
    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'RAGOL SYSTEM');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);
        $logoUrl = rtrim(config('app.url'), '/').'/'.ltrim(config('starter.logo'), '/');
        $base = rtrim(config('app.url', 'https://app.example.com'), '/');

        $url = "{$base}/auth/reset-password?token="
            .urlencode($this->resetToken)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset()).'&email_locale='.$this->locale;

        return $this->mail()
            ->subject($this->translate('auth::mail.reset.subject', ['app' => $appName]))
            ->view('auth::notifications.reset-password', [
                'locale' => $this->userLocale,
                'appName' => $appName,
                'url' => $url,
                'user' => $notifiable,
                'logoUrl' => $logoUrl,
                'minutes' => $minutes,
            ]);
    }

    /**
     * Database channel - creates persistent notification in admin_notifications table.
     */
    public function toDatabase($notifiable): array
    {
        $appName = config('app.name', 'RAGOL SYSTEM');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        return [
            'type' => 'warning',
            'title' => $this->translate('auth::mail.reset.subject', ['app' => $appName]),
            'message' => "Lien de réinitialisation valable {$minutes} minutes.",
            'data' => [
                'action_url' => 'auth/reset-password',
                'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
                'email' => $notifiable->getEmailForPasswordReset(),
            ],
        ];
    }
}
