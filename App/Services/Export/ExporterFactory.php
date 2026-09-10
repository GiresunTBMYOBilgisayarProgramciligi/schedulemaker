<?php

namespace App\Services\Export;

use App\Enums\ExamType;
use App\DTOs\ScheduleExportFilterDTO;
use App\Services\Export\Excel\ExamScheduleExcelExporter;
use App\Services\Export\Excel\ExamScheduleListExcelExporter;
use App\Services\Export\Excel\LessonScheduleExcelExporter;
use App\Services\Export\Excel\LessonScheduleListExcelExporter;
use App\Services\Export\Ics\ExamScheduleIcsExporter;
use App\Services\Export\Ics\LessonScheduleIcsExporter;
use App\Services\Export\Json\ExamScheduleJsonExporter;
use App\Services\Export\Json\LessonScheduleJsonExporter;
use Exception;

/**
 * İstek parametrelerine göre doğru exporter sınıfını üretir.
 *
 * Kullanım:
 *   $exporter = ExporterFactory::create($dto, 'excel');
 *   $exporter->export($dto, $showOptions);
 */
class ExporterFactory
{
    /**
     * @param ScheduleExportFilterDTO|array $filters Doğrulanmış filtre DTO veya dizisi (type alanı zorunlu)
     * @param string $format 'excel' veya 'ics'
     * @return ScheduleExporterInterface
     * @throws Exception
     */
    public static function create(ScheduleExportFilterDTO|array $filters, string $format): ScheduleExporterInterface
    {
        $type = $filters instanceof ScheduleExportFilterDTO 
            ? $filters->type 
            : ($filters['type'] ?? 'lesson');

        $isExam = ExamType::isExamType($type);

        return match ($format) {
            'excel' => $isExam ? new ExamScheduleExcelExporter()     : new LessonScheduleExcelExporter(),
            'list'  => $isExam ? new ExamScheduleListExcelExporter() : new LessonScheduleListExcelExporter(),
            'json'  => $isExam ? new ExamScheduleJsonExporter()      : new LessonScheduleJsonExporter(),
            'ics'   => $isExam ? new ExamScheduleIcsExporter()       : new LessonScheduleIcsExporter(),
            default => throw new Exception("Desteklenmeyen format: {$format}"),
        };
    }
}
