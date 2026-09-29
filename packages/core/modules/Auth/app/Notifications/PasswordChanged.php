<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class PasswordChanged extends QueuedAuthMail
{
    public function __construct(public string $occurredAt, public bool $byAdministrator = false, public bool $mustChangePassword = false, ?string $locale = null)
    {
        parent::__construct($locale);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mail()
            ->subject($this->translate('auth::account_mail.password_subject'))
            ->greeting($this->translate('auth::account_mail.greeting', ['name' => $notifiable->name]))
            ->line($this->translate('auth::account_mail.'.($this->byAdministrator ? 'password_admin' : 'password_changed')))
            ->line($this->translate('auth::account_mail.time', ['time' => $this->occurredAt]));

        if ($this->mustChangePassword) {
            $mail->line($this->translate('auth::account_mail.must_change'));
        }

        return $mail->line($this->translate('auth::account_mail.no_password'))
            ->line($this->translate('auth::account_mail.unexpected'))
            ->action($this->translate('auth::account_mail.reset_action'), $this->forgotPasswordUrl());
    }
}
