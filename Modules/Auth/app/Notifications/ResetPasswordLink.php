<?php

namespace Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token, ?string $locale = null)
    {
        $this->locale = $locale ?? app()->getLocale() ?? 'fr';
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }
    /*
    public function toMail($notifiable): MailMessage
    {
        //URL vers ton frontend (SPA ou site)
        $url = config('app.frontend_url', 'https://app.example.com')
            . '/auth/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        // Chemin ou URL du logo de ton app
        $logoUrl = config('app.logo_url', 'http://127.0.0.1:5173/resources/images/logo.png');

        // Nom de ton app (affiché dans le mail)
        $appName = config('app.name', 'RAGOL SYSTEM');


        return (new MailMessage)
            ->subject("🔐 Réinitialisation de votre mot de passe — {$appName}")
            ->view('auth::notifications.reset-password', [
                'appName' => $appName,
                'url' => $url,
                'user' => $notifiable,
                'logoUrl' => $logoUrl,
            ]);
    }*/

    public function toMail($notifiable): MailMessage
    {
        app()->setLocale($this->locale); // ✅ force la langue ici

        $appName = config('app.name', 'RAGOL SYSTEM');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $logoUrl = config('app.logo_url', 'https://example.com/logo.png');
        $base = rtrim(config('app.frontend_url', 'https://app.example.com'), '/');

        $url = "{$base}/auth/reset-password?token="
            .urlencode($this->token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject(__('auth::mail.reset.subject', ['app' => $appName]))
            ->view('auth::notifications.reset-password', [
                'locale' => $this->locale,
                'appName' => $appName,
                'url' => $url,
                'user' => $notifiable,
                'logoUrl' => $logoUrl,
                'minutes' => $minutes,
            ]);
    }
}
