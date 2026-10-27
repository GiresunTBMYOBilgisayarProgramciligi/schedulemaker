<?php

namespace Tests\Unit;

use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\ClassroomType;
use App\Enums\ExamType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Exceptions\NotFoundException;
use App\Services\Export\Excel\ExamScheduleExcelExporter;
use App\Services\Export\Excel\ExamScheduleListExcelExporter;
use App\Services\Export\Excel\LessonScheduleExcelExporter;
use App\Services\Export\Excel\LessonScheduleListExcelExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\BaseTestCase;
use function App\Helpers\getSettingValue;

/**
 * Excel çıktılarının üretilen çalışma kitabı üzerinden doğrulanması:
 * tercih/uygun değil kayıtlarının dışlanması, rowspan birleştirmesi ve boş çıktı davranışı.
 */
class ExcelGridExportTest extends BaseTestCase
{
    private const SEMESTER = 'Güz';
    private const YEAR     = '2026 - 2027';

    private int $deptId;
    private int $programId;
    private int $classroomId;
    private int $lecturerId;

    protected function setUp(): void
    {
        parent::setUp();

        $unitId = $this->insert('units', ['name' => 'Test Fakültesi', 'type' => 'fakulte', 'active' => 1]);
        $buildingId = $this->insert('buildings', ['name' => 'Test Bina', 'unit_id' => $unitId]);
        $this->classroomId = $this->insert('classrooms', [
            'name'        => 'D-101',
            'building_id' => $buildingId,
            'type'        => ClassroomType::CLASSROOM->value,
        ]);
        $this->deptId = $this->insert('departments', ['name' => 'Test Bölümü', 'unit_id' => $unitId, 'active' => 1]);
        $this->programId = $this->insert('programs', ['name' => 'Test Programı', 'department_id' => $this->deptId, 'active' => 1]);
        $this->lecturerId = $this->insert('users', [
            'name'          => 'Hoca',
            'last_name'     => 'Test',
            'mail'          => 'hoca_' . rand(1000, 9999) . '@test.com',
            'password'      => password_hash('123456', PASSWORD_DEFAULT),
            'role'          => 'lecturer',
            'department_id' => $this->deptId,
        ]);
        $adminId = $this->insert('users', [
            'name'      => 'Admin',
            'last_name' => 'User',
            'mail'      => 'admin_' . rand(1000, 9999) . '@test.com',
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'admin',
        ]);

        $this->resetAuth();
        $_SESSION[$_ENV["SESSION_KEY"] ?? 'user_id'] = $adminId;
    }

    private function insertLesson(string $code, string $name): int
    {
        return $this->insert('lessons', [
            'name'           => $name,
            'code'           => $code,
            'type'           => LessonType::COMPULSORY->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 1,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::CLASSROOM->value,
        ]);
    }

    private function insertItem(int $scheduleId, int $dayIndex, string $start, string $end, string $status, ?int $lessonId): void
    {
        $data = $lessonId === null ? [] : [[
            'lesson_id'    => $lessonId,
            'lecturer_id'  => $this->lecturerId,
            'classroom_id' => $this->classroomId,
        ]];

        $this->insert('schedule_items', [
            'schedule_id' => $scheduleId,
            'day_index'   => $dayIndex,
            'start_time'  => $start,
            'end_time'    => $end,
            'status'      => $status,
            'data'        => serialize($data),
        ]);
    }

    private function insertProgramLessonSchedule(): int
    {
        return $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 1,
            'type'          => 'lesson',
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
            'is_published'  => 1,
        ]);
    }

    private function programFilters(): ScheduleExportFilterDTO
    {
        return ScheduleExportFilterDTO::fromArray([
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 1,
            'type'          => 'lesson',
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
    }

    private function loadSheet(string $rawContent): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($path, $rawContent);
        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }

    private function sheetText(Worksheet $sheet): string
    {
        $text = '';
        foreach ($sheet->toArray(null, false, false) as $row) {
            $text .= implode(' | ', array_map(fn($v) => (string) $v, $row)) . "\n";
        }

        return $text;
    }

    public function testLessonGridExcludesPreferredAndUnavailableItems(): void
    {
        $scheduleId = $this->insertProgramLessonSchedule();
        $lessonId = $this->insertLesson('TST101', 'Görünen Ders');
        $this->insertItem($scheduleId, 0, '09:00:00', '10:50:00', 'single', $lessonId);
        $this->insertItem($scheduleId, 1, '14:00:00', '15:50:00', 'preferred', $this->insertLesson('TST102', 'Tercih Dersi'));
        $this->insertItem($scheduleId, 2, '14:00:00', '15:50:00', 'unavailable', $this->insertLesson('TST103', 'Uygun Değil Dersi'));

        $text = $this->sheetText($this->loadSheet(
            (new LessonScheduleExcelExporter())->getRawContent($this->programFilters(), ScheduleExportOptionsDTO::fromArray([]))
        ));

        $this->assertStringContainsString('Görünen Ders', $text);
        $this->assertStringNotContainsString('Tercih Dersi', $text);
        $this->assertStringNotContainsString('Uygun Değil Dersi', $text);
    }

    public function testListExportExcludesPreferredAndUnavailableItems(): void
    {
        $scheduleId = $this->insertProgramLessonSchedule();
        $this->insertItem($scheduleId, 0, '09:00:00', '10:50:00', 'single', $this->insertLesson('TST201', 'Liste Dersi'));
        $this->insertItem($scheduleId, 1, '14:00:00', '15:50:00', 'preferred', $this->insertLesson('TST202', 'Tercih Liste'));
        $this->insertItem($scheduleId, 2, '14:00:00', '15:50:00', 'unavailable', null);

        $text = $this->sheetText($this->loadSheet(
            (new LessonScheduleListExcelExporter())->getRawContent($this->programFilters(), ScheduleExportOptionsDTO::fromArray([]))
        ));

        $this->assertStringContainsString('Liste Dersi', $text);
        $this->assertStringNotContainsString('Tercih Liste', $text);
        // Yalnızca tek veri satırı (gün sütunu) yazılmış olmalı
        $this->assertSame(1, substr_count($text, 'Pazartesi'));
        $this->assertSame(0, substr_count($text, 'Salı'));
        $this->assertSame(0, substr_count($text, 'Çarşamba'));
    }

    /**
     * Rowspan yalnızca hücredeki item kümesi aynen devam ediyorsa uygulanmalıdır.
     * Eski davranış sadece ilk item'ı karşılaştırdığından, 08:30'da başlayan uzun sınavın hücresi
     * 09:00'da başlayan kısa sınavın satırını da kapsıyor ve kısa sınav çıktıdan kayboluyordu.
     */
    public function testExamGridDoesNotMergeWhenOnlySomeItemsContinue(): void
    {
        $scheduleId = $this->insert('schedules', [
            'owner_type'    => OwnerType::CLASSROOM->value,
            'owner_id'      => $this->classroomId,
            'semester_no'   => 0,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
            'is_published'  => 1,
        ]);
        $this->insertItem($scheduleId, 0, '08:30:00', '10:00:00', 'single', $this->insertLesson('SNV101', 'Uzun Sınav'));
        $this->insertItem($scheduleId, 0, '09:00:00', '09:30:00', 'single', $this->insertLesson('SNV102', 'Kısa Sınav'));
        $this->insertItem($scheduleId, 0, '11:00:00', '12:00:00', 'single', $this->insertLesson('SNV103', 'Tek Sınav'));

        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => OwnerType::CLASSROOM->value,
            'owner_id'      => $this->classroomId,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
        $sheet = $this->loadSheet(
            (new ExamScheduleExcelExporter())->getRawContent($filters, ScheduleExportOptionsDTO::fromArray([]))
        );

        $cellRowOf = function (string $needle) use ($sheet): int {
            foreach ($sheet->getRowIterator() as $row) {
                $value = $sheet->getCell('B' . $row->getRowIndex())->getValue();
                if (str_contains((string) $value, $needle)) {
                    return $row->getRowIndex();
                }
            }
            $this->fail("'{$needle}' içeren hücre bulunamadı");
        };

        $mergedStarts = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = explode(':', $range);
            $mergedStarts[$start] = $end;
        }

        // 08:30: yalnızca uzun sınav, 09:00: ikisi birlikte, 09:30: yine yalnızca uzun sınav — hiçbiri birleştirilmez
        $sharedRow = $cellRowOf('Kısa Sınav');
        $this->assertStringContainsString('Uzun Sınav', (string) $sheet->getCell("B{$sharedRow}")->getValue());
        foreach ([$sharedRow - 1, $sharedRow, $sharedRow + 1] as $r) {
            $this->assertArrayNotHasKey("B{$r}", $mergedStarts, "B{$r} birleştirilmemeli");
        }
        $this->assertStringNotContainsString('Kısa Sınav', (string) $sheet->getCell('B' . ($sharedRow - 1))->getValue());
        $this->assertStringNotContainsString('Kısa Sınav', (string) $sheet->getCell('B' . ($sharedRow + 1))->getValue());

        // Tek başına iki slot süren sınav birleştirilir
        $singleRow = $cellRowOf('Tek Sınav');
        $this->assertSame('B' . ($singleRow + 1), $mergedStarts["B{$singleRow}"] ?? null);
    }

    /**
     * Liste çıktısının sütun başlıklarını döndürür ("Ders Adı" içeren ilk satır).
     */
    private function listHeaders(Worksheet $sheet): array
    {
        foreach ($sheet->toArray(null, false, false) as $row) {
            if (in_array('Ders Adı', $row, true)) {
                return array_values(array_filter($row, fn($v) => $v !== null && $v !== ''));
            }
        }
        $this->fail('Liste başlık satırı bulunamadı');
    }

    public function testListExportRespectsColumnOptions(): void
    {
        $scheduleId = $this->insertProgramLessonSchedule();
        $this->insertItem($scheduleId, 0, '09:00:00', '10:50:00', 'single', $this->insertLesson('OPT101', 'Seçenek Dersi'));

        $exportWith = fn(array $options) => $this->loadSheet(
            (new LessonScheduleListExcelExporter())->getRawContent($this->programFilters(), ScheduleExportOptionsDTO::fromArray($options))
        );

        $this->assertSame(
            ['Ders Kodu', 'Ders Adı', 'Program/Bölüm', 'Derslik', 'Hoca', 'Gün', 'Başlangıç Saati', 'Bitiş Saati'],
            $this->listHeaders($exportWith([]))
        );

        $sheet = $exportWith(['show_code' => 0, 'show_lecturer' => 0, 'show_program' => 0]);
        $this->assertSame(['Ders Adı', 'Derslik', 'Gün', 'Başlangıç Saati', 'Bitiş Saati'], $this->listHeaders($sheet));
        $text = $this->sheetText($sheet);
        $this->assertStringNotContainsString('OPT101', $text);
        $this->assertStringNotContainsString('Hoca Test', $text);
        $this->assertStringContainsString('Seçenek Dersi', $text);
    }

    public function testListExportRespectsInternshipOption(): void
    {
        $scheduleId = $this->insertProgramLessonSchedule();
        $this->insertItem($scheduleId, 0, '09:00:00', '10:50:00', 'single', $this->insertLesson('NRM101', 'Normal Ders'));
        $internshipId = $this->insert('lessons', [
            'name'           => 'Staj Dersi',
            'code'           => 'STJ101',
            'type'           => LessonType::INTERNSHIP->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 1,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::CLASSROOM->value,
        ]);
        $this->insertItem($scheduleId, 1, '09:00:00', '10:50:00', 'single', $internshipId);

        $exportText = fn(bool $showInternship) => $this->sheetText($this->loadSheet(
            (new LessonScheduleListExcelExporter())->getRawContent(
                $this->programFilters(),
                ScheduleExportOptionsDTO::fromArray(['show_internship' => $showInternship ? 1 : 0])
            )
        ));

        $this->assertStringNotContainsString('Staj Dersi', $exportText(false));
        $this->assertStringContainsString('Normal Ders', $exportText(false));
        $this->assertStringContainsString('Staj Dersi', $exportText(true));
    }

    public function testExamListSeparatesLecturerAndObserverColumns(): void
    {
        $scheduleId = $this->insert('schedules', [
            'owner_type'    => OwnerType::CLASSROOM->value,
            'owner_id'      => $this->classroomId,
            'semester_no'   => 0,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
            'is_published'  => 1,
        ]);
        // Derslik kaydında slottaki hoca gözetmendir; dersin hocası ders atamasından bulunmalıdır
        $lessonId = $this->insertLesson('SNV201', 'Liste Sınavı');
        $this->insertItem($scheduleId, 0, '09:00:00', '10:00:00', 'single', $lessonId);
        $lessonLecturerId = $this->insert('users', [
            'name'          => 'Dersin',
            'last_name'     => 'Hocası',
            'mail'          => 'ders_hocasi_' . rand(1000, 9999) . '@test.com',
            'password'      => password_hash('123456', PASSWORD_DEFAULT),
            'role'          => 'lecturer',
            'department_id' => $this->deptId,
        ]);
        $this->insert('lesson_assignments', [
            'lesson_id'     => $lessonId,
            'lecturer_id'   => $lessonLecturerId,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);

        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => OwnerType::CLASSROOM->value,
            'owner_id'      => $this->classroomId,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
        $exportWith = fn(array $options) => $this->loadSheet(
            (new ExamScheduleListExcelExporter())->getRawContent($filters, ScheduleExportOptionsDTO::fromArray($options))
        );

        // Derslik çıktısında Derslik sütunu yoktur; gözetmen ayrı sütunda yer alır
        $sheet = $exportWith([]);
        $this->assertSame(
            ['Ders Kodu', 'Ders Adı', 'Program/Bölüm', 'Hoca', 'Gözetmen', 'Tarih', 'Başlangıç Saati', 'Bitiş Saati'],
            $this->listHeaders($sheet)
        );
        $dataRow = array_values(array_filter(
            $sheet->toArray(null, false, false),
            fn($row) => ($row[1] ?? null) === 'Liste Sınavı'
        ))[0];
        $this->assertSame('Dersin Hocası', $dataRow[3]);
        $this->assertSame('Hoca Test', $dataRow[4]);

        $sheet = $exportWith(['show_observer' => 0, 'show_lecturer' => 0]);
        $this->assertSame(
            ['Ders Kodu', 'Ders Adı', 'Program/Bölüm', 'Tarih', 'Başlangıç Saati', 'Bitiş Saati'],
            $this->listHeaders($sheet)
        );
        $this->assertStringNotContainsString('Hoca Test', $this->sheetText($sheet));
        $this->assertStringNotContainsString('Dersin Hocası', $this->sheetText($sheet));

        // Izgara çıktısında da dersin hocası yazılır
        $gridText = $this->sheetText($this->loadSheet(
            (new ExamScheduleExcelExporter())->getRawContent($filters, ScheduleExportOptionsDTO::fromArray([]))
        ));
        $this->assertStringContainsString('(Dersin Hocası)', $gridText);
    }

    public function testProgramExamGridRespectsShowProgramOption(): void
    {
        $scheduleId = $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 1,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
            'is_published'  => 1,
        ]);
        $this->insertItem($scheduleId, 0, '09:00:00', '10:00:00', 'single', $this->insertLesson('SNV301', 'Program Sınavı'));

        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 1,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
        $exportText = fn(bool $showProgram) => $this->sheetText($this->loadSheet(
            (new ExamScheduleExcelExporter())->getRawContent(
                $filters,
                ScheduleExportOptionsDTO::fromArray(['show_program' => $showProgram ? 1 : 0])
            )
        ));

        $this->assertStringContainsString('(Test Programı-1)', $exportText(true));
        $text = $exportText(false);
        $this->assertStringContainsString('Program Sınavı', $text);
        $this->assertStringNotContainsString('(Test Programı-1)', $text);
    }

    /**
     * Derslik bazlı sınav listesinde "başlıkları gösterme" seçiliyse tüm derslikler,
     * derslik adı ilk sütunda olacak şekilde tek tabloda, tarih ve saate göre sıralı yazılır.
     */
    public function testClassroomExamListHideTitlesWritesSingleTable(): void
    {
        $buildingId = $this->insert('buildings', ['name' => 'Tek Tablo Bina', 'unit_id' => $this->insert('units', ['name' => 'Tek Tablo Birim', 'type' => 'fakulte', 'active' => 1])]);
        // [gün, başlangıç, bitiş] — derslikler arası karışık sırada eklenir
        $slots = [
            'Salon-A' => [[1, '09:00:00', '10:00:00'], [0, '08:30:00', '09:00:00']],
            'Salon-B' => [[0, '09:00:00', '10:00:00']],
        ];
        $lessonNo = 0;
        foreach ($slots as $name => $classroomSlots) {
            $classroomId = $this->insert('classrooms', [
                'name'        => $name,
                'building_id' => $buildingId,
                'type'        => ClassroomType::CLASSROOM->value,
            ]);
            $scheduleId = $this->insert('schedules', [
                'owner_type'    => OwnerType::CLASSROOM->value,
                'owner_id'      => $classroomId,
                'semester_no'   => 0,
                'type'          => ExamType::MIDTERM->value,
                'semester'      => self::SEMESTER,
                'academic_year' => self::YEAR,
                'is_published'  => 1,
            ]);
            foreach ($classroomSlots as [$day, $start, $end]) {
                $lessonNo++;
                $this->insertItem($scheduleId, $day, $start, $end, 'single', $this->insertLesson("TT{$lessonNo}", "Tek Tablo Sınavı {$lessonNo}"));
            }
        }

        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => 'building',
            'owner_id'      => $buildingId,
            'type'          => ExamType::MIDTERM->value,
            'semester'      => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
        $exportWith = fn(array $options) => $this->loadSheet(
            (new ExamScheduleListExcelExporter())->getRawContent($filters, ScheduleExportOptionsDTO::fromArray($options))
        );

        // Varsayılan: her derslik kendi başlığı ve tablosuyla
        $text = $this->sheetText($exportWith([]));
        $this->assertSame(2, substr_count($text, 'Ders Adı'));
        $this->assertStringContainsString('Salon-A Ara Sınav Programı', $text);

        // Tek tablo: başlık çubuğu yok, sütun başlıkları bir kez, ilk sütun derslik adı
        $sheet = $exportWith(['hide_titles' => 1]);
        $text  = $this->sheetText($sheet);
        $this->assertSame(1, substr_count($text, 'Ders Adı'));
        $this->assertStringNotContainsString('Ara Sınav Programı |', $text);
        $headers = $this->listHeaders($sheet);
        $this->assertSame('Derslik', $headers[0]);
        $this->assertNotEmpty($sheet->getAutoFilter()->getRange(), 'Tek tabloda filtre okları olmalı');

        $rows = array_values(array_filter(
            $sheet->toArray(null, false, false),
            fn($row) => str_starts_with((string) ($row[2] ?? ''), 'Tek Tablo Sınavı')
        ));
        $dateCol  = array_search('Tarih', $headers, true);
        $startCol = array_search('Başlangıç Saati', $headers, true);

        // Tarih + saat sırası: Pzt 08:30 Salon-A, Pzt 09:00 Salon-B, Sal 09:00 Salon-A
        $this->assertSame(['Salon-A', 'Salon-B', 'Salon-A'], array_map(fn($row) => $row[0], $rows));
        $this->assertSame(['08:30', '09:00', '09:00'], array_map(fn($row) => $row[$startCol], $rows));

        // Tarih hücresi metin değil Excel tarih değeridir (sıralanabilir)
        $startDate = new \DateTime(getSettingValue('midterm_start_date', 'exam'));
        $expected  = [
            $startDate->format('Y-m-d'),
            $startDate->format('Y-m-d'),
            (clone $startDate)->modify('+1 day')->format('Y-m-d'),
        ];
        $this->assertSame($expected, array_map(
            fn($row) => ExcelDate::excelToDateTimeObject($row[$dateCol])->format('Y-m-d'),
            $rows
        ));
    }

    public function testExportThrowsWhenNothingToWrite(): void
    {
        $this->expectException(NotFoundException::class);

        (new LessonScheduleExcelExporter())->export($this->programFilters(), ScheduleExportOptionsDTO::fromArray([]));
    }
}
