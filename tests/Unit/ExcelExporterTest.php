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
                $val = $this->sheet->getCell($cell)->getValue();
                return ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) ? $val->getPlainText() : (string) $val;
            }

            public function getDrawings(): array
            {
                return $this->sheet->getDrawingCollection()->getArrayCopy();
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

        $nextRow = $exporter->testWriteFileTitle($dto);
        $this->assertEquals(3, $nextRow);

        $headerValue = $exporter->getSheetCellValue('A1');
        $this->assertStringContainsString('GİRESUN ÜNİVERSİTESİ', $headerValue);
        $this->assertStringContainsString('GÖRELE GÜZEL SANATLAR FAKÜLTESİ', $headerValue);

        // İki yanda kurumsal logoların eklendiğini doğrula
        $drawings = $exporter->getDrawings();
        $this->assertCount(2, $drawings);
        $this->assertEquals('A1', $drawings[0]->getCoordinates());
        $this->assertEquals('Kurum Logosu (Sol)', $drawings[0]->getName());
        $this->assertStringEndsWith('1', $drawings[1]->getCoordinates());
        $this->assertEquals('Kurum Logosu (Sağ)', $drawings[1]->getName());
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

    public function testScheduleExportFilterValidatorSupportsShowInternship(): void
    {
        $validator = new \App\Validators\Schedule\ScheduleExportFilterValidator();
        $dto = $validator->getDTO([
            'type'            => 'lesson',
            'owner_type'      => 'program',
            'owner_id'        => $this->programId,
            'show_internship' => 'true',
        ], 'exportScheduleAction');

        $this->assertTrue($dto->show_internship);
    }

    public function testGuestExportDoesNotIncludeInternshipSummary(): void
    {
        unset($_SESSION['user_id']);

        $exporter = new LessonScheduleExcelExporter();
        $filterDto = ScheduleExportFilterDTO::fromArray([
            'type'            => 'lesson',
            'owner_type'      => 'program',
            'owner_id'        => $this->programId,
            'semester'        => 'Güz',
            'academic_year'   => '2025 - 2026',
        ]);
        $optionsDto = \App\DTOs\ScheduleExportOptionsDTO::fromArray([
            'show_internship' => true,
        ]);

        $content = $exporter->getRawContent($filterDto, $optionsDto);
        $this->assertNotEmpty($content);
        // Misafir kullanıcı için staj tablosu Excel içeriğine eklenmemeli
        $this->assertStringNotContainsString('STAJ / İŞLETMEDE MESLEKİ EĞİTİM BİLGİLERİ', $content);
    }

    public function testClassroomAndBuildingExportHeaderEndsAtColumnF(): void
    {
        $exporter = new class extends LessonScheduleExcelExporter {
            public function testWriteFileTitle(ScheduleExportFilterDTO|array $filters): int
            {
                return $this->writeFileTitle($filters);
            }

            public function getMergeCells(): array
            {
                return $this->sheet->getMergeCells();
            }

            public function getHeaderLastCol(): string
            {
                return $this->headerLastCol;
            }
        };

        // Derslik export'u 6 sütunla (A..F) sınırlı olmalı
        $classroomDto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'lesson',
            'owner_type'    => 'classroom',
            'owner_id'      => 1,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);
        $exporter->testWriteFileTitle($classroomDto);
        $this->assertEquals('F', $exporter->getHeaderLastCol());
        $this->assertContains('A1:F2', $exporter->getMergeCells());

        // Bina export'u da derslikleri listelediği için 6 sütunla (A..F) sınırlı olmalı
        $buildingExporter = new class extends LessonScheduleExcelExporter {
            public function testWriteFileTitle(ScheduleExportFilterDTO|array $filters): int
            {
                return $this->writeFileTitle($filters);
            }

            public function getHeaderLastCol(): string
            {
                return $this->headerLastCol;
            }
        };
        $buildingDto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'lesson',
            'owner_type'    => 'building',
            'owner_id'      => 1,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);
        $buildingExporter->testWriteFileTitle($buildingDto);
        $this->assertEquals('F', $buildingExporter->getHeaderLastCol());
    }

    public function testRightLogoIsAlignedWithinHeaderBounds(): void
    {
        $exporter = new LessonScheduleExcelExporter();
        $filterDto = ScheduleExportFilterDTO::fromArray([
            'type'          => 'lesson',
            'owner_type'    => 'program',
            'owner_id'      => $this->programId,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);

        $raw = $exporter->getRawContent($filterDto);
        $this->assertNotEmpty($raw);

        $tempFile = tempnam(sys_get_temp_dir(), 'excel_test_');
        file_put_contents($tempFile, $raw);

        $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
        $sheet = $ss->getActiveSheet();
        unlink($tempFile);

        $drawings = $sheet->getDrawingCollection();
        $this->assertCount(2, $drawings);

        $left = $drawings[0];
        $right = $drawings[1];

        $this->assertEquals('Kurum Logosu (Sol)', $left->getName());
        $this->assertEquals('A1', $left->getCoordinates());
        $this->assertEquals(60, $left->getHeight());

        $this->assertEquals('Kurum Logosu (Sağ)', $right->getName());
        $this->assertEquals(60, $right->getHeight());
        // Sağ logo en sağ sınır olan K (veya J) sütunu içinde yer almalı
        $this->assertMatchesRegularExpression('/^[A-K]1$/', $right->getCoordinates());
    }
}
