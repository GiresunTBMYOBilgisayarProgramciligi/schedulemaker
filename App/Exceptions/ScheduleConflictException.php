<?php

namespace App\Exceptions;

use App\Models\ScheduleItem;
use App\Models\Schedule;

/**
 * Schedule çakışması exception'ı
 * 
 * Ders programı çakışması tespit edildiğinde fırlatılır
 */
class ScheduleConflictException extends AppException
{
    /**
     * @param string $message Hata mesajı
     * @param ScheduleItem|null $conflictingItem Çakışan item
     * @param Schedule|null $schedule İlgili schedule
     * @param array $context Ek context
     */
    public function __construct(
        string $message,
        ?ScheduleItem $conflictingItem = null,
        ?Schedule $schedule = null,
        array $context = []
    ) {
        if ($conflictingItem) {
            $context['conflicting_item_id'] = $conflictingItem->id;
            $context['conflicting_time'] = [
                'start' => $conflictingItem->start_time,
                'end' => $conflictingItem->end_time,
                'day_index' => $conflictingItem->day_index
            ];
        }

        if ($schedule) {
            $context['schedule_id'] = $schedule->id;
            $context['schedule_name'] = $schedule->getScheduleScreenName();
        }

        parent::__construct($message, $context);
    }
}
