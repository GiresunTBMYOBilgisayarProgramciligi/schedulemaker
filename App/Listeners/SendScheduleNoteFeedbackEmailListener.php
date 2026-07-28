<?php

namespace App\Listeners;

use App\Events\ScheduleNoteStatusUpdatedEvent;
use App\Mailers\ScheduleNoteMailer;

class SendScheduleNoteFeedbackEmailListener
{
    /**
     * Olayı dinleyip e-postayı gönderir.
     * 
     * @param ScheduleNoteStatusUpdatedEvent $event
     */
    public function handle(ScheduleNoteStatusUpdatedEvent $event): void
    {
        $mailer = new ScheduleNoteMailer();
        $mailer->sendStatusFeedbackEmail($event->note, $event->lecturer, $event->editor);
    }
}
