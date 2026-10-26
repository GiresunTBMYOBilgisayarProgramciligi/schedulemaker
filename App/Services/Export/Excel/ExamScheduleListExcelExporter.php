<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\DTOs\ScheduleExportFilterDTO;
use App\Enums\ExamType;
use App\Models\ScheduleItem;

/**
 * Sınav programlarını (Ara Sınav / Final / Bütünleme) satır bazlı liste formatında
 * Excel olarak dışa aktarır.
 *
 * Tablo çizimi, sütunlar ve veri formatı LessonScheduleListExcelExporter ile aynıdır;
 * sadece başlık etiketi ve dosya başlığı sınav türüne göre özelleşir.
 */
class ExamScheduleListExcelExporter extends LessonScheduleListExcelExporter
{
    protected string $defaultFileTitle = 'Sınav Programı';

    /**
     * Sınav türüne göre başlık etiketini belirler.
     */
    protected function resolvePeriodLabel(ScheduleExportFilterDTO $filters): string
    {
        $type = $filters->type ?? 'exam';

        return match ($type) {
            ExamType::MIDTERM->value => 'ARA SINAV PROGRAMI (LİSTE)',
            ExamType::FINAL->value   => 'FİNAL SINAV PROGRAMI (LİSTE)',
            ExamType::MAKEUP->value  => 'BÜTÜNLEME SINAV PROGRAMI (LİSTE)',
            default                  => 'SINAV PROGRAMI (LİSTE)',
        };
    }

    /**
     * Sınav programında sınav birleştirmesiyle bağlanan dersler (examChildLessons) gösterilir.
     *
     * @return \App\Models\Lesson[]
     */
    protected function resolveChildLessons(\App\Models\Lesson $lesson): array
    {
        return $lesson->examChildLessons ?? [];
    }

    /**
     * Sınavlarda salon bilgisi ScheduleItem detail['assignments'] içindedir.
     * Gözetmenlik/derslik bazlı kayıtlarda assignments yoktur, salon slot verisinden okunur.
     */
    protected function resolveClassroomName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        if (!isset($item->detail['assignments'])) {
            return parent::resolveClassroomName($item, $data, $scheduleType);
        }

        $names = [];
        foreach ($item->detail['assignments'] ?? [] as $assignment) {
            if (!empty($assignment['classroom_name'])) {
                $names[] = $assignment['classroom_name'];
            }
        }

        return implode("\n", array_unique($names));
    }

    /**
     * Sınavlarda hoca sütunu gözetmenleri içerir (detail['assignments'] içinden).
     * Assignments olmayan kayıtlarda slot verisindeki gözetmen kullanılır.
     */
    protected function resolveLecturerName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        if (!isset($item->detail['assignments'])) {
            // Gözetmenlik kaydı: sınavı yapılan dersin hocası
            return $item->detail['lesson_lecturer'] ?? parent::resolveLecturerName($item, $data, $scheduleType);
        }

        $names = [];
        foreach ($item->detail['assignments'] ?? [] as $assignment) {
            if (!empty($assignment['observers']) && is_array($assignment['observers'])) {
                foreach ($assignment['observers'] as $observer) {
                    $name = is_array($observer) ? ($observer['name'] ?? '') : (is_string($observer) ? $observer : '');
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            } elseif (!empty($assignment['observer_name'])) {
                $names[] = $assignment['observer_name'];
            }
        }

        return implode("\n", array_unique($names));
    }
}
