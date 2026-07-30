<?php

namespace App\Listeners;

use App\Enums\ScheduleNoteStatus;
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
        // 'Görüldü' (read) veya 'Beklemede' (pending) durumları için e-posta bildirimi gönderilmez
        if ($event->note->status === ScheduleNoteStatus::READ->value || $event->note->status === ScheduleNoteStatus::PENDING->value) {
            return;
        }

        $mailer = new ScheduleNoteMailer();
        $mailer->sendStatusFeedbackEmail($event->note, $event->lecturer, $event->editor);
    }
}
