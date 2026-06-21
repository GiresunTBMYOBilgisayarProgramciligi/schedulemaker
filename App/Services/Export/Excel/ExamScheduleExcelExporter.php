<?php

namespace App\Services\Export\Excel;

use App\Controllers\ScheduleController;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use JetBrains\PhpStorm\NoReturn;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;

/**
 * Sınav programını (Ara Sınav / Final / Bütünleme) Excel formatında dışa aktarır.
 *
 * Ders programından farkları:
 *  - Final programı 2 haftalık olabilir
 *  - Gözetmen isimleri (show_observer) seçeneği desteklenir
 *  - Hücre başlığında sınav tarihi/saati gösterilebilir
 */
class ExamScheduleExcelExporter extends BaseExcelExporter
{
    /**
     * @param array $filters    Doğrulanmış filtre dizisi
     * @param array $showOptions ['show_code', 'show_lecturer', 'show_program', 'show_observer']
     */
    #[NoReturn]
    public function export(array $filters, array $showOptions): void
    {
        $username  = $this->logContext()['username'] ?? "Sistem";
        $ownerType = $filters['owner_type'] ?? 'bilinmeyen';
        $type      = $filters['type'];
        $this->logger()->info(
            "{$username} {$ownerType} bazlı {$type} programı çıktısı aldı.",
            $this->logContext()
        );

        $scheduleController = new ScheduleController();
        $maxDayIndex        = getSettingValue('maxDayIndex', 'exam', 4);
        $colsPerDay         = ($filters['owner_type'] === 'classroom') ? 1 : 2;
        $totalCols          = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol            = Coordinate::stringFromColumnIndex($totalCols);

        $row             = $this->writeFileTitle($filters);
        $scheduleFilters = $this->filterBuilder->build($filters);

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            // Final programı 2 haftalık olabilir
            $weekCount   = ($filters['type'] === 'final-exam') ? 2 : 1;
            $maxDayIndex = getSettingValue('maxDayIndex', 'exam', 4);
            $scheduleRows = $scheduleController->prepareScheduleRows($schedule, 'excel', $maxDayIndex);

            foreach ($scheduleRows as $weekIndex => $slots) {
                $isClassroom = ($scheduleFilter['type'] === 'classroom');
                $colsPerDay  = $isClassroom ? 1 : 2;
                $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
                $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

                // Hafta numarası başlığı (birden fazla hafta varsa)
                if ($weekIndex > 0) {
                    $row += 1;
                    $this->sheet->setCellValue("A{$row}", ($weekIndex + 1) . ". HAFTA");
                    $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $this->sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $row++;
                }

                // Program başlığı (mavi bar — ders programından ayırt etmek için)
                $titleText = $scheduleFilter['title'] . ($weekCount > 1 ? " (" . ($weekIndex + 1) . ". Hafta)" : "");
                $this->sheet->setCellValue("A{$row}", $titleText);
                $this->sheet->getStyle("A{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setSize(11);
                // Sınav programı için mavi başlık rengi
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->getColor()->setRGB('FFFFFF');

                $firstCell = "A" . ($row + 1);
                $row++;

                // Gün başlıkları
                $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
                $this->sheet->setCellValue("A{$row}", "Saat");

                for ($i = 0; $i <= $maxDayIndex; $i++) {
                    $colIdx = $i * $colsPerDay + 2;
                    $col    = Coordinate::stringFromColumnIndex($colIdx);
                    $this->sheet->setCellValue("{$col}{$row}", $days[$i]);
                    $this->sheet->getStyle("{$col}{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    if (!$isClassroom) {
                        $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                        $this->sheet->setCellValue("{$sCol}{$row}", "S / Gözetmen");
                        $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                        $this->sheet->getColumnDimension($sCol)->setWidth(15);
                    }
                }

                $this->sheet->getColumnDimension('A')->setWidth(12);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $row++;

                // Slot satırları
                foreach ($slots as $slot) {
                    $timeLabel = $slot['slotStartTime']->format('H:i') . " - " . $slot['slotEndTime']->format('H:i');
                    $this->sheet->setCellValue("A{$row}", $timeLabel);
                    $this->sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($i = 0; $i <= $maxDayIndex; $i++) {
                        $colIdx = $i * $colsPerDay + 2;
                        $col    = Coordinate::stringFromColumnIndex($colIdx);
                        $dayKey = 'day' . $i;

                        if (isset($slot['days'][$dayKey]) && $slot['days'][$dayKey] !== null) {
                            $items = is_array($slot['days'][$dayKey]) ? $slot['days'][$dayKey] : [$slot['days'][$dayKey]];

                            $combinedContent   = new RichText();
                            $combinedClassroom = new RichText();
                            $combinedContent->createText("\n");
                            $combinedClassroom->createText("\n");

                            foreach ($items as $idx => $item) {
                                $this->formatItem(
                                    $item,
                                    $scheduleFilter['type'],
                                    $showOptions,
                                    $combinedContent,
                                    $combinedClassroom,
                                    $idx > 0
                                );
                            }

                            $combinedContent->createText("\n");
                            $combinedClassroom->createText("\n");

                            $this->sheet->setCellValue("{$col}{$row}", $combinedContent);

                            if (!$isClassroom) {
                                $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                                $this->sheet->setCellValue("{$sCol}{$row}", $combinedClassroom);
                                $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()
                                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                                    ->setVertical(Alignment::VERTICAL_CENTER)
                                    ->setWrapText(true);
                            }
                        }

                        $this->sheet->getStyle("{$col}{$row}")->getAlignment()
                            ->setWrapText(true)
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    $row++;
                }

                // Kenarlıklar
                $this->sheet->getStyle($firstCell . ":" . $lastCol . ($row - 1))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row += 2;
            }
        }

        $this->autoSizeColumns('A', $lastCol);

        $fileTitle      = $scheduleFilters[array_key_last($scheduleFilters)]['file_title'] ?? 'Program';
        $exportFileName = $filters['academic_year'] . " " . $filters['semester'] . " " . $fileTitle . ".xlsx";
        $this->download($exportFileName);
    }

    /**
     * Sınav programı item'ini RichText olarak formatlar.
     *
     * Veri yapısı iki farklı şekilde gelebilir:
     *   A) Program/Ders bazlı kayit:
     *      - data: [{lesson_id, lecturer_id: null, classroom_id: null}]
     *      - detail: {assignments: [{observer_id, observer_name, classroom_id, classroom_name}, ...]}
     *   B) Gözetmen/Derslik bazlı kayit:
     *      - data: [{lesson_id, lecturer_id: Y, classroom_id: Z}]
     *      - detail: {program_item_id: ..., reference_type: 'exam_assignment'}
     */
    private function formatItem(
        ScheduleItem $item,
        string $scheduleType,
        array $options,
        RichText &$richContent,
        RichText &$richClassroom,
        bool $addSeparator = false
    ): void {
        $slotDatas   = $item->getSlotDatas();
        $assignments = $item->detail['assignments'] ?? null; // Program bazlı kayitta dolu olur

        foreach ($slotDatas as $index => $data) {
            if ($addSeparator || $index > 0) {
                $richContent->createText("\n" . str_repeat('═', 20) . "\n");
                $richClassroom->createText("\n" . str_repeat('═', 5) . "\n");
                $addSeparator = false;
            }

            // Ders Adı
            $lessonName = $data->lesson->getFullName(addGroup: true);
            if ($options['show_code'] && !empty($data->lesson->code)) {
                $lessonName = "[" . $data->lesson->code . "] " . $lessonName;
            }
            $richContent->createTextRun($lessonName)->getFont()->setBold(true);

            // Hoca Adı (sadece program/ders bazlı görünümde gösterilir)
            if ($options['show_lecturer'] && $scheduleType !== 'user' && $data->lecturer) {
                $richContent->createText("\n(" . $data->lecturer->getFullName() . ")");
            }

            // Program / Bölüm Adı
            if ($options['show_program'] && ($scheduleType === 'user' || $scheduleType === 'classroom')) {
                $programNames = [];
                if ($data->lesson->program) {
                    $programNames[] = $data->lesson->program->name . "-" . getClassFromSemesterNo($data->lesson->semester_no);
                }
                if (!empty($data->lesson->examChildLessons)) {
                    foreach ($data->lesson->examChildLessons as $child) {
                        if ($child->program) {
                            $programNames[] = $child->program->name . "-" . getClassFromSemesterNo($data->lesson->semester_no);
                        }
                    }
                }
                $programNamesStr = implode(', ', array_unique($programNames));
                if ($programNamesStr) {
                    $richContent->createText("\n(" . $programNamesStr . ")");
                }
            }

            // ── Derslik ve Gözetmen Sütunu ─────────────────────────────────────
            if ($assignments !== null) {
                // A) Program bazlı kayit: detail['assignments'] üzera satır satır Gözetmen + Derslik
                $classroomLines = [];
                foreach ($assignments as $assignment) {
                    $classroomLines[] = ($assignment['classroom_name'] ?? '');
                }
                $richClassroom->createText(implode("\n", array_unique($classroomLines)));

                if ($options['show_observer'] ?? false) {
                    $observerNames = array_map(
                        fn($a) => ($a['observer_name'] ?? ''),
                        $assignments
                    );
                    $richContent->createText("\n" . implode(', ', array_filter($observerNames)));
                }
            } else {
                // B) Gözetmen/Derslik bazlı kayit: data içinde classroom bilgisi var
                if ($scheduleType !== 'classroom' && $data->classroom) {
                    $richClassroom->createText($data->classroom->name);
                }
            }
        }
    }
}
