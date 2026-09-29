<?php

namespace Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class QueuedAuthMail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120, 300];

    public function __construct(?string $locale = null)
    {
        $this->onConnection(config('auth.mail_connection', 'database'))->onQueue('auth-mail')->afterCommit();
        $selected = $locale ?? app()->getLocale();
        $this->locale(in_array($selected, ['fr', 'en'], true) ? $selected : 'fr');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function translate(string $key, array $parameters = []): string
    {
        return __($key, ['app' => config('app.name')] + $parameters, $this->locale);
    }

    protected function mail(): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->from(config('mail.from.address'), config('app.name'))
            ->view('auth::notifications.account', [
                'emailLocale' => $this->locale,
                'logoUrl' => rtrim(config('app.url'), '/').'/'.ltrim(config('starter.logo'), '/'),
            ]);
    }

    protected function loginUrl(): string
    {
        return rtrim(config('app.url'), '/').'/auth/login';
    }

    protected function forgotPasswordUrl(): string
    {
        return rtrim(config('app.url'), '/').'/auth/forgot-password';
    }
}
