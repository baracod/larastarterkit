<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class LoginAlert extends QueuedAuthMail
{
    public function __construct(public string $occurredAt, public string $ipAddress, public string $userAgent, ?string $locale = null)
    {
        parent::__construct($locale);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail()
            ->subject($this->translate('auth::account_mail.login_subject'))
            ->greeting($this->translate('auth::account_mail.greeting', ['name' => $notifiable->name]))
            ->line($this->translate('auth::account_mail.login_body'))
            ->line($this->translate('auth::account_mail.time', ['time' => $this->occurredAt]))
            ->line($this->translate('auth::account_mail.ip', ['ip' => $this->ipAddress]))
            ->line($this->translate('auth::account_mail.device', ['device' => $this->userAgent]))
            ->line($this->translate('auth::account_mail.unexpected'))
            ->action($this->translate('auth::account_mail.reset_action'), $this->forgotPasswordUrl());
    }
}
