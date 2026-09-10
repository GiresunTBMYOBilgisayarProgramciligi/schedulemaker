<?php

declare(strict_types=1);

namespace App\Services\Export\Json;

use App\DTOs\ScheduleExportFilterDTO;

/**
 * Sınav programlarını (Ara Sınav / Final / Bütünleme) JSON formatında dışa aktarır.
 *
 * Veri çıkarma ve dışa aktarma mantığı LessonScheduleJsonExporter ile aynıdır;
 * sadece dosya adı üretimi sınav programına göre özelleştirilmiştir.
 */
class ExamScheduleJsonExporter extends LessonScheduleJsonExporter
{
    /**
     * Sınav programı için dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterDTO       = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $scheduleFilters = $this->filterBuilder->build($filterDTO);
        $lastKey         = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title']))
            ? $scheduleFilters[$lastKey]['file_title']
            : 'Sinav-Programi';
        $academicYear    = $filterDTO->academic_year ?? '';
        $semester        = $filterDTO->semester ?? '';
        $baseName        = $academicYear . '-' . $semester . '-' . $fileTitle . '-Liste';

        return $this->slugify($baseName) . '.json';
    }
}
