<?php

namespace App\Services\Export;

use App\Enums\ExamType;
use App\Services\Export\Excel\ExamScheduleExcelExporter;
use App\Services\Export\Excel\LessonScheduleExcelExporter;
use App\Services\Export\Ics\ExamScheduleIcsExporter;
use App\Services\Export\Ics\LessonScheduleIcsExporter;
use Exception;

/**
 * İstek parametrelerine göre doğru exporter sınıfını üretir.
 *
 * Kullanım:
 *   $exporter = ExporterFactory::create($filters, 'excel');
 *   $exporter->export($filters, $showOptions);
 */
class ExporterFactory
{
    /**
     * @param array  $filters Doğrulanmış filtre dizisi (type alanı zorunlu)
     * @param string $format  'excel' veya 'ics'
     * @return ScheduleExporterInterface
     * @throws Exception
     */
    public static function create(array $filters, string $format): ScheduleExporterInterface
    {
        $type   = $filters['type'] ?? 'lesson';
        $isExam = ExamType::isExamType($type);

        return match ($format) {
            'excel' => $isExam ? new ExamScheduleExcelExporter() : new LessonScheduleExcelExporter(),
            'ics'   => $isExam ? new ExamScheduleIcsExporter()   : new LessonScheduleIcsExporter(),
            default => throw new Exception("Desteklenmeyen format: {$format}"),
        };
    }
}
