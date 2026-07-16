<?php

namespace App\Listeners;

use App\Events\UserForgotPasswordEvent;
use App\Mailers\PasswordResetMailer;

class SendPasswordResetEmailListener
{
    /**
     * Olayı dinleyip e-postayı gönderir.
     * 
     * @param UserForgotPasswordEvent $event
     */
    public function handle(UserForgotPasswordEvent $event): void
    {
        $mailer = new PasswordResetMailer();
        $mailer->sendResetLink($event->user, $event->token);
    }
}
