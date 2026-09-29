<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class LoginInstructions extends QueuedAuthMail
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail()
            ->subject($this->translate('auth::account_mail.instructions_subject'))
            ->greeting($this->translate('auth::account_mail.greeting', ['name' => $notifiable->name]))
            ->line($this->translate('auth::account_mail.instructions_intro'))
            ->line($this->translate('auth::account_mail.instructions_email', ['email' => $notifiable->email]))
            ->line($this->translate('auth::account_mail.instructions_password'))
            ->line($this->translate('auth::account_mail.instructions_forgot', ['url' => $this->forgotPasswordUrl()]))
            ->line($this->translate('auth::account_mail.instructions_change'))
            ->line($this->translate('auth::account_mail.instructions_access'))
            ->action($this->translate('auth::account_mail.login_action'), $this->loginUrl());
    }
}
