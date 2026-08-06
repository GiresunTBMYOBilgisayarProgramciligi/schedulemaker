<?php

namespace App\Listeners;

use App\Events\ScheduleChangesNotifiedEvent;
use App\Mailers\ScheduleMailer;
use App\Models\User;
use Exception;

class SendScheduleChangesEmailListener
{
    public function handle(ScheduleChangesNotifiedEvent $event): void
    {
        try {
            $lecturer = (new User())->find($event->lecturer_id);
            if ($lecturer && !empty($lecturer->mail)) {
                $mailer = new ScheduleMailer();
                $mailer->sendScheduleChangesNotification($lecturer, $event->changes);
            }
        } catch (Exception $e) {
            error_log("SendScheduleChangesEmailListener error: " . $e->getMessage());
        }
    }
}
