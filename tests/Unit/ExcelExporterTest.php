<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\DTOs\ScheduleExportFilterDTO;
use App\Services\Export\Excel\LessonScheduleExcelExporter;

class ExcelExporterTest extends BaseTestCase
{
    private int $unitId;
    private int $deptId;
    private int $programId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitId = $this->insert('units', [
            'name' => 'Görele Güzel Sanatlar Fakültesi',
            'type' => 'fakulte',
            'active' => 1
        ]);

        $this->deptId = $this->insert('departments', [
            'name' => 'Grafik Tasarımı',
            'unit_id' => $this->unitId,
            'active' => 1
        ]);

        $this->programId = $this->insert('programs', [
            'name' => 'Grafik',
            'department_id' => $this->deptId,
            'active' => 1
        ]);
    }

    public function testHeaderIncludesResolvedUnitName(): void
    {
        $exporter = new class extends LessonScheduleExcelExporter {
            public function testResolveUnitName(ScheduleExportFilterDTO|array $filters): string
            {
                return $this->resolveUnitName($filters);
            }

            public function getSheetCellValue(string $cell): mixed
            {
                return $this->sheet->getCell($cell)->getValue();
            }

            public function testWriteFileTitle(ScheduleExportFilterDTO|array $filters): int
            {
                return $this->writeFileTitle($filters);
            }
        };

        $dto = ScheduleExportFilterDTO::fromArray([
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $this->programId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);

        $unitName = $exporter->testResolveUnitName($dto);
        $this->assertEquals('Görele Güzel Sanatlar Fakültesi', $unitName);

        $exporter->testWriteFileTitle($dto);
        $headerValue = $exporter->getSheetCellValue('A2');

        $this->assertStringContainsString('GİRESUN ÜNİVERSİTESİ', $headerValue);
        $this->assertStringContainsString('GÖRELE GÜZEL SANATLAR FAKÜLTESİ', $headerValue);
    }

    public function testGetFileNameIncludesSpecificNames(): void
    {
        $exporter = new LessonScheduleExcelExporter();

        // Program bazlı
        $filters = [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $this->programId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];
        $fileName = $exporter->getFileName($filters);
        $this->assertStringContainsString('grafik', $fileName);
        $this->assertStringContainsString('ders-programi', $fileName);

        // Bölüm bazlı
        $deptFilters = [
            'type' => 'lesson',
            'owner_type' => 'department',
            'owner_id' => $this->deptId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];
        $deptFileName = $exporter->getFileName($deptFilters);
        $this->assertStringContainsString('grafik-tasarimi', $deptFileName);

        // Birim bazlı
        $unitFilters = [
            'type' => 'lesson',
            'owner_type' => 'unit',
            'owner_id' => $this->unitId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];
        $unitFileName = $exporter->getFileName($unitFilters);
        $this->assertStringContainsString('gorele-guzel-sanatlar-fakultesi', $unitFileName);
    }
}
