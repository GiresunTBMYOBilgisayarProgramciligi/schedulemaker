<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Services\Export\ExporterFactory;
use App\DTOs\ScheduleExportFilterDTO;
use App\Services\Export\Excel\LessonScheduleExcelExporter;
use App\Services\Export\Excel\ExamScheduleExcelExporter;
use App\Services\Export\Ics\LessonScheduleIcsExporter;
use App\Services\Export\Ics\ExamScheduleIcsExporter;

class ExporterFactoryTest extends BaseTestCase
{
    public function testCreateExcelLessonExporter(): void
    {
        $dto = ScheduleExportFilterDTO::fromArray([
            'type' => 'lesson',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);

        $exporter = ExporterFactory::create($dto, 'excel');
        $this->assertInstanceOf(LessonScheduleExcelExporter::class, $exporter);
    }

    public function testCreateExcelExamExporter(): void
    {
        $dto = ScheduleExportFilterDTO::fromArray([
            'type' => 'midterm-exam',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);

        $exporter = ExporterFactory::create($dto, 'excel');
        $this->assertInstanceOf(ExamScheduleExcelExporter::class, $exporter);
    }

    public function testCreateIcsExporters(): void
    {
        $lessonDto = ScheduleExportFilterDTO::fromArray([
            'type' => 'lesson',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);
        $this->assertInstanceOf(LessonScheduleIcsExporter::class, ExporterFactory::create($lessonDto, 'ics'));

        $examDto = ScheduleExportFilterDTO::fromArray([
            'type' => 'final-exam',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);
        $this->assertInstanceOf(ExamScheduleIcsExporter::class, ExporterFactory::create($examDto, 'ics'));
    }

    public function testUnsupportedFormatThrowsException(): void
    {
        $dto = ScheduleExportFilterDTO::fromArray([
            'type' => 'lesson',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);

        $this->expectException(\Exception::class);
        ExporterFactory::create($dto, 'pdf_unsupported');
    }
}
