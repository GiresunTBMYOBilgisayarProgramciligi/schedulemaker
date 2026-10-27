<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\ExamType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Services\Schedule\ExamScheduleService;

/**
 * Sınav programlarını (Ara Sınav / Final / Bütünleme) satır bazlı liste formatında
 * Excel olarak dışa aktarır.
 *
 * Tablo çizimi ve veri formatı LessonScheduleListExcelExporter ile aynıdır; başlık etiketi,
 * dosya başlığı ve Hoca / Gözetmen sütunları sınava göre özelleşir.
 */
class ExamScheduleListExcelExporter extends LessonScheduleListExcelExporter
{
    protected string $defaultFileTitle = 'Sınav Programı';

    /**
     * Hoca sınav programı, hocanın dersleri ve gözetmenlik görevleriyle birleşik olarak hazırlanır.
     */
    protected function findSchedule(array $filter): ?Schedule
    {
        return (new ExamScheduleService())->findScheduleWithItems($filter);
    }

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
     * Sınav listesinde ders hocası ve gözetmenler ayrı sütunlardadır.
     * Hoca sütunu hoca bazlı sınav programında da gösterilir (birleşik görünümde
     * hocanın gözetmen olduğu sınavların ders hocası farklıdır).
     */
    protected function resolveColumns(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $options): array
    {
        $columns = [];
        foreach (parent::resolveColumns($filters, $options) as $key => $header) {
            if ($key === 'day' || $key === 'date') {
                if ($options->showLecturer) {
                    $columns['lecturer'] = 'Hoca';
                }
                if ($options->showObserver) {
                    $columns['observer'] = 'Gözetmen';
                }
            }
            if ($key !== 'lecturer') {
                $columns[$key] = $header;
            }
        }

        return $columns;
    }

    /**
     * Sınavı yapılan dersin hocası (bkz. resolveExamLessonLecturerName).
     */
    protected function resolveLecturerName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        return $this->resolveExamLessonLecturerName($item, $data);
    }

    /**
     * Gözetmenler program bazlı kayıtlarda detail['assignments'] içindedir.
     * Gözetmenlik/derslik bazlı kayıtlarda gözetmen slot verisindeki hocadır.
     */
    protected function resolveObserverName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        if (!isset($item->detail['assignments'])) {
            return $data->lecturer?->getFullName() ?? '';
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
