<?php

namespace App\Exceptions;

use App\Models\Lesson;

/**
 * Ders saati aşımı exception'ı
 * 
 * Dersin haftalık saati aşıldığında fırlatılır
 */
class LessonHourExceededException extends AppException
{
    /**
     * @param Lesson $lesson Ders
     * @param int $remainingSize Kalan saat (negatif değer)
     * @param string $scheduleType Program tipi (lesson, midterm-exam, vb.)
     * @param array $context Ek context
     */
    public function __construct(
        Lesson $lesson,
        int $remainingSize,
        string $scheduleType,
        array $context = []
    ) {
        $message = sprintf(
            'Ders saati aşıldı: %s (%s), Kalan: %d',
            $lesson->name,
            $lesson->code,
            $remainingSize
        );

        $context['lesson_id'] = $lesson->id;
        $context['lesson_name'] = $lesson->name;
        $context['lesson_code'] = $lesson->code;
        $context['remaining_size'] = $remainingSize;
        $context['schedule_type'] = $scheduleType;

        parent::__construct($message, $context);
    }
}
