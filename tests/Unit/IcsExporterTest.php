<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Services\Export\Ics\LessonScheduleIcsExporter;
use App\Services\Export\Ics\ExamScheduleIcsExporter;
use App\Services\Export\ExporterFactory;
use App\DTOs\ScheduleExportFilterDTO;

class IcsExporterTest extends BaseTestCase
{
    public function testExporterFactoryCreatesIcsExporters(): void
    {
        $lessonDto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);
        $exporter = ExporterFactory::create($lessonDto, 'ics');
        $this->assertInstanceOf(LessonScheduleIcsExporter::class, $exporter);

        $examDto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'midterm-exam',
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);
        $examExporter = ExporterFactory::create($examDto, 'ics');
        $this->assertInstanceOf(ExamScheduleIcsExporter::class, $examExporter);
    }

    public function testLessonIcsExporterGetFileName(): void
    {
        $exporter = new LessonScheduleIcsExporter();
        $dto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'lesson',
            'owner_type'    => 'department',
            'owner_id'      => 1,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);

        $fileName = $exporter->getFileName($dto);
        $this->assertStringEndsWith('.ics', $fileName);
        $this->assertStringContainsString('guz', $fileName);
    }

    public function testIcsRawContentGeneration(): void
    {
        $exporter = new LessonScheduleIcsExporter();
        $filters = [
            'type'          => 'lesson',
            'owner_type'    => 'program',
            'owner_id'      => 999999, // Olmayan bir ID
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ];

        $content = $exporter->getRawContent($filters, []);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }
}
