<?php

namespace App\Helpers;

use App\Models\Schedule;
use App\Models\ScheduleItem;

/**
 * ScheduleViewHelper
 *
 * Schedule tablo görünümlerinde kullanılan ortak yardımcı fonksiyonlar.
 * lesson-card ve empty-slot elemanları için data attribute'larını oluşturur.
 */
class ScheduleViewHelper
{
    /**
     * Lesson card için data attribute dizisi oluşturur.
     *
     * @param ScheduleItem $scheduleItem İlgili schedule item
     * @param object $slotData Slot verisi (lesson, lecturer, classroom)
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $draggable Sürüklenebilir mi
     * @param string $type Tablo tipi: 'lesson' veya 'exam'
     * @return array Key-value şeklinde HTML attribute dizisi
     */
    public static function buildLessonCardAttributes(
        ScheduleItem $scheduleItem,
        object       $slotData,
        Schedule     $schedule,
        bool         $draggable,
        string       $type = 'lesson'
    ): array
    {
        $cssClass = "lesson-card " . $slotData->lesson->getScheduleCSSClass();
        if ($type === 'exam') {
            $cssClass = "lesson-card h-100 m-0 " . $slotData->lesson->getScheduleCSSClass();
        }

        $attrs = [
            'draggable' => $draggable ? 'true' : 'false',
            'class' => $cssClass,
            'data-schedule-item-id' => $scheduleItem->id,
            'data-group-no' => $slotData->lesson->group_no,
            'data-lesson-id' => $slotData->lesson->id,
            'data-lesson-code' => $slotData->lesson->code,
            'data-lesson-name' => $type === 'exam'
                ? $slotData->lesson->name
                : $slotData->lesson->getFullName(addCode: true),// todo bu incelenmeli farklılık gerkli mi 
            'data-size' => $slotData->lesson->size,
            'data-lecturer-id' => $slotData->lecturer?->id,
            'data-lecturer-name' => $slotData->lecturer?->getFullName(),
            'data-classroom-id' => $slotData->classroom?->id,
            'data-classroom-name' => $slotData->classroom?->name,
            'data-classroom-size' => $slotData->classroom?->class_size,
            'data-classroom-exam-size' => $slotData->classroom?->exam_size,
            'data-status' => $scheduleItem->status,
        ];

        // Exam tablosunda detail attribute'u eklenir
        if ($type === 'exam') {
            $attrs['data-detail'] = json_encode($scheduleItem->detail);
        }

        // Program bilgisi (program dışındaki schedule türlerinde)
        if ($schedule->owner_type !== 'program') {
            $attrs['data-program-id'] = $slotData->lesson->program_id;
            $attrs['data-program-name'] = $slotData->lesson->program?->name;

            // Lesson tablosunda child lesson program bilgileri de eklenir
            if ($type === 'lesson' && count($slotData->lesson->childLessons) > 0) {
                foreach ($slotData->lesson->childLessons as $childLesson) {
                    $attrs['data-child-lessons-' . $childLesson->id . '-program-id'] = $childLesson->program_id;
                    $attrs['data-child-lessons-' . $childLesson->id . '-program-name'] = $childLesson->program?->name;
                }
            }
        }

        return $attrs;
    }

    /**
     * Attribute dizisini HTML string'e dönüştürür.
     *
     * @param array $attrs Key-value attribute dizisi
     * @return string HTML attribute string
     */
    public static function renderAttributes(array $attrs): string
    {
        $result = "";
        foreach ($attrs as $key => $val) {
            $result .= " $key=\"" . htmlspecialchars((string)($val ?? "")) . "\"";
        }
        return $result;
    }

    /**
     * Available lessons panelindeki ders kartı için data attribute dizisi oluşturur.
     *
     * Tablo içindeki buildLessonCardAttributes'ten farklı olarak ScheduleItem yerine
     * doğrudan Lesson modeli veya dummy obje ile çalışır.
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı (preferred/unavailable)
     * @return array Key-value şeklinde HTML attribute dizisi
     */
    public static function buildAvailableLessonAttributes(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): array
    {
        // Draggable belirleme
        $draggable = 'true';
        if (!$isDummy) {
            if (!is_null($lesson->parent_lesson_id)
                || $schedule->academic_year != getSettingValue('academic_year')
                || $schedule->semester != getSettingValue('semester')
            ) {
                $draggable = 'false';
            }
        }

        // CSS sınıfı belirleme
        if ($isDummy) {
            $status = $lesson->status ?? '';
            $cssClass = "dummy w-100 slot-" . $status;
        } else {
            /** @var \App\Models\Lesson $lesson */
            $cssClass = "lesson-card w-100 " . $lesson->getScheduleCSSClass();
        }

        $attrs = [
            'id' => 'available-lesson-' . $lesson->id,
            'draggable' => $draggable,
            'class' => $cssClass,
            'data-lesson-id' => $lesson->id,
            'data-lesson-hours' => $lesson->hours ?? 1,
            'data-group-no' => $isDummy ? 0 : $lesson->group_no,
            'data-lesson-code' => $lesson->code,
            'data-lecturer-id' => $lesson->lecturer_id ?? null,
            'data-status' => $isDummy ? ($lesson->status ?? '') : '',
            'data-program-id' => $isDummy ? null : $lesson->program_id,
            'data-size' => $isDummy ? null : ($lesson->size ?? 0),
            // Sağ-tık menü için isimler
            'data-program-name' => $isDummy ? null : ($lesson->program->name ?? null),
            'data-lecturer-name' => $isDummy ? null : ($lesson->lecturer?->getFullName()),
            'data-lesson-name' => $isDummy ? null : $lesson->getFullName(addCode: true),
        ];

        if ($isDummy) {
            $attrs['data-is-dummy'] = 'true';
        }

        return $attrs;
    }

    /**
     * Available lessons panelindeki ders adını formatlar.
     *
     * Schedule tipi ve owner type'a göre farklı formatlama uygular.
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı
     * @return string Formatlanmış ders adı
     */
    public static function getAvailableLessonName(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): string
    {
        if ($isDummy) {
            return $lesson->name ?? '';
        }

        /** @var \App\Models\Lesson $lesson */
        if ($schedule->type === 'lesson') {
            // Ders programında grup bilgisi eklenir
            if (in_array($schedule->owner_type, ['user', 'classroom'])) {
                return $lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: true);
            }
            return $lesson->getFullName(addGroup: true);
        }

        // Sınav programında sadece ders adı
        if (in_array($schedule->owner_type, ['user', 'classroom'])) {
            return $lesson->getFullName(addProgram: true, addClassNumber: true);
        }
        return $lesson->getFullName();
    }

    /**
     * Available lessons panelindeki bilgi metnini üretir.
     *
     * Ders programı → "X Saat", Sınav programı → "X Kişi"
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı
     * @return string Bilgi metni
     */
    public static function getAvailableLessonInfoText(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): string
    {
        if ($isDummy) {
            return '';
        }

        return $schedule->type === 'lesson'
            ? ($lesson->hours ?? 0) . ' Saat'
            : ($lesson->size ?? 0) . ' Kişi';
    }

    /**
     * Ders kartının sürüklenebilir olup olmadığını belirler.
     *
     * @param object $slotData Slot verisi
     * @param Schedule $schedule Üst schedule
     * @param bool $onlyTable Salt okunur tablo mu
     * @param bool $preferenceMode Tercih modu mu
     * @return bool
     */
    public static function isDraggable(
        object   $slotData,
        Schedule $schedule,
        bool     $onlyTable = false,
        bool     $preferenceMode = false
    ): bool
    {
        if (!is_null($slotData->lesson->parent_lesson_id)) {
            return false;
        }
        if ($schedule->academic_year != \App\Helpers\getSettingValue('academic_year')) {
            return false;
        }
        if ($schedule->semester != \App\Helpers\getSettingValue('semester')) {
            return false;
        }
        if ($onlyTable || $preferenceMode) {
            return false;
        }
        return true;
    }
}
