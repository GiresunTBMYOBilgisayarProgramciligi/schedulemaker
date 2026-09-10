<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\Core\Gate;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\PermissionType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;

/**
 * Ders programını satır bazlı liste formatında Excel olarak dışa aktarır.
 *
 * Sütunlar:
 *   Ders Kodu | Ders Adı | Program/Bölüm | Derslik | Hoca | Gün | Başlangıç Saati | Bitiş Saati
 */
class LessonScheduleListExcelExporter extends BaseExcelExporter
{
    /** @var string[] Gün isimleri (0=Pazartesi … 6=Pazar) */
    protected const DAYS = [
        'Pazartesi',
        'Salı',
        'Çarşamba',
        'Perşembe',
        'Cuma',
        'Cumartesi',
        'Pazar',
    ];

    /** @var string[] Başlık satırı */
    protected const HEADERS = [
        'Ders Kodu',
        'Ders Adı',
        'Program/Bölüm',
        'Derslik',
        'Hoca',
        'Gün',
        'Başlangıç Saati',
        'Bitiş Saati',
    ];

    protected string $defaultFileTitle = 'Ders Programı';

    /**
     * @param ScheduleExportFilterDTO  $filters     Doğrulanmış filtre DTO'su
     * @param ScheduleExportOptionsDTO $showOptions Gösterim seçenekleri DTO'su
     */
    protected function buildSpreadsheet(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): void
    {
        $scheduleFilters = $this->filterBuilder->build($filters);
        $lastFilterKey   = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastFilterKey !== null && isset($scheduleFilters[$lastFilterKey]['file_title']))
            ? $scheduleFilters[$lastFilterKey]['file_title']
            : $this->defaultFileTitle;

        $username = $this->logContext()['username'] ?? 'Misafir';
        $this->logger()->info(
            "{$username} {$fileTitle} Liste Excel çıktısı aldı.",
            $this->logContext()
        );

        $totalCols = count(self::HEADERS);
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);

        $this->writeListFileTitle($filters, $lastCol);

        $row = 5;

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with('items')
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            $row = $this->writeScheduleHeader($scheduleFilter['title'], $row, $lastCol);
            $row = $this->writeColumnHeaders($row, $lastCol);

            $dataStartRow = $row;

            foreach ($schedule->items as $item) {
                /** @var ScheduleItem $item */
                $row = $this->writeItem($item, $scheduleFilter['type'], $showOptions, $row);
            }

            if ($row > $dataStartRow) {
                $this->sheet->getStyle("A{$dataStartRow}:{$lastCol}" . ($row - 1))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }

            $row++;
        }

        $this->autoSizeColumns('A', $lastCol);
    }

    /**
     * Dönem/program başlık etiketini belirler (alt sınıflar ezebilir).
     */
    protected function resolvePeriodLabel(ScheduleExportFilterDTO $filters): string
    {
        return 'HAFTALIK DERS PROGRAMI (LİSTE)';
    }

    /**
     * Liste formatı için dosya başlıklarını yazar (A2–A3).
     */
    protected function writeListFileTitle(ScheduleExportFilterDTO $filters, string $lastCol): void
    {
        $universityName = getSettingValue('university_name', 'general', 'Giresun Üniversitesi');
        $unitName       = $this->resolveUnitName($filters);
        $periodLabel    = $this->resolvePeriodLabel($filters);

        $academicYear = $filters->academic_year ?? getSettingValue('academic_year');
        $semester     = $filters->semester ?? getSettingValue('semester');

        $upperUniversity = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $universityName), 'UTF-8');
        $upperUnit       = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $unitName), 'UTF-8');
        $titleLine1      = trim("{$upperUniversity} {$upperUnit}");
        $titleLine2      = trim("{$academicYear} {$semester} YARIYILI {$periodLabel}");

        $this->sheet->setCellValue('A2', $titleLine1);
        $this->sheet->mergeCells("A2:{$lastCol}2");
        $this->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $this->sheet->getStyle('A2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->setCellValue('A3', $titleLine2);
        $this->sheet->mergeCells("A3:{$lastCol}3");
        $this->sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $this->sheet->getStyle('A3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Program başlık çubuğunu (turuncu bar) yazar.
     */
    protected function writeScheduleHeader(string $title, int $row, string $lastCol): int
    {
        $this->sheet->setCellValue("A{$row}", $title);
        $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFont()->setBold(true)->setSize(11);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffbf00');

        return $row + 1;
    }

    /**
     * Kolon başlıklarını yazar.
     */
    protected function writeColumnHeaders(int $row, string $lastCol): int
    {
        foreach (self::HEADERS as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $this->sheet->setCellValue("{$col}{$row}", $header);
        }

        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFont()->setBold(true);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return $row + 1;
    }

    /**
     * Bir ScheduleItem'in verilerini liste satırları olarak yazar.
     * Birden fazla ders içerebilen slotlar için her ders ayrı satır olur.
     *
     * @throws \Exception
     */
    protected function writeItem(
        ScheduleItem $item,
        string $scheduleType,
        ScheduleExportOptionsDTO $options,
        int $row
    ): int {
        $dayName   = self::DAYS[$item->day_index] ?? '';
        $startTime = $item->getShortStartTime();
        $endTime   = $item->getShortEndTime();

        $slotDatas = $item->getSlotDatas();

        foreach ($slotDatas as $data) {
            $lesson = $data->lesson;

            // Ders Kodu
            $code = $lesson?->code ?? '';

            // Ders Adı (grup harfi dahil)
            $lessonName = $lesson?->getFullName(addGroup: true) ?? '';

            // Program/Bölüm
            $programName = '';
            if ($lesson?->program) {
                $programName = $lesson->program->name;
                if ($lesson->semester_no) {
                    $programName .= ' - ' . getClassFromSemesterNo($lesson->semester_no);
                }
            }
            if (!empty($lesson?->childLessons)) {
                $childPrograms = [];
                foreach ($lesson->childLessons as $child) {
                    if ($child?->program) {
                        $childProgramStr = $child->program->name;
                        if ($lesson->semester_no) {
                            $childProgramStr .= ' - ' . getClassFromSemesterNo($lesson->semester_no);
                        }
                        $childPrograms[] = $childProgramStr;
                    }
                }
                if (!empty($childPrograms)) {
                    $allPrograms = array_unique(array_merge([$programName], $childPrograms));
                    $programName = implode(', ', array_filter($allPrograms));
                }
            }

            // Derslik (derslik tabanlı programda zaten derslik bilgisi schedule sahibidir)
            $classroomName = '';
            if ($scheduleType !== 'classroom' && !empty($data->classroom)) {
                $classroomName = $data->classroom->name;
            }

            // Hoca (hoca tabanlı programda hoca bilgisi schedule sahibidir)
            $lecturerName = '';
            if ($scheduleType !== 'user' && !empty($data->lecturer)) {
                $lecturerName = $data->lecturer->getFullName();
            }

            $this->sheet->setCellValue("A{$row}", $code);
            $this->sheet->setCellValue("B{$row}", $lessonName);
            $this->sheet->setCellValue("C{$row}", $programName);
            $this->sheet->setCellValue("D{$row}", $classroomName);
            $this->sheet->setCellValue("E{$row}", $lecturerName);
            $this->sheet->setCellValue("F{$row}", $dayName);
            $this->sheet->setCellValue("G{$row}", $startTime);
            $this->sheet->setCellValue("H{$row}", $endTime);

            foreach (['A', 'F', 'G', 'H'] as $centerCol) {
                $this->sheet->getStyle("{$centerCol}{$row}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }
            $this->sheet->getStyle("B{$row}:E{$row}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $this->sheet->getRowDimension($row)->setRowHeight(20);

            $row++;
        }

        return $row;
    }

    /**
     * Dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterDTO       = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $scheduleFilters = $this->filterBuilder->build($filterDTO);
        $lastKey         = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title']))
            ? $scheduleFilters[$lastKey]['file_title']
            : 'Program';
        $academicYear    = $filterDTO->academic_year ?? '';
        $semester        = $filterDTO->semester ?? '';
        $baseName        = $academicYear . '-' . $semester . '-' . $fileTitle . '-Liste';

        return $this->slugify($baseName) . '.xlsx';
    }
}
