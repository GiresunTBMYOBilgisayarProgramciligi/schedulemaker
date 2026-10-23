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
     * Sınavlarda salon bilgisi slot verisinde değil, ScheduleItem detail['assignments'] içindedir.
     */
    protected function resolveClassroomName(ScheduleItem $item, object $data, string $scheduleType): string
    {
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
     */
    protected function resolveLecturerName(ScheduleItem $item, object $data, string $scheduleType): string
    {
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
