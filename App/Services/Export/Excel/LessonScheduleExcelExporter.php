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
 * Ders programını Excel formatında dışa aktarır.
 */
class LessonScheduleExcelExporter extends BaseExcelExporter
{
    /**
     * @param array $filters    Doğrulanmış filtre dizisi
     * @param array $showOptions ['show_code', 'show_lecturer', 'show_program']
     */
    #[NoReturn]
    public function export(array $filters, array $showOptions): void
    {
        $username  = $this->logContext()['username'] ?? "Sistem";
        $ownerType = $filters['owner_type'] ?? 'bilinmeyen';
        $this->logger()->info(
            "{$username} {$ownerType} bazlı ders programı çıktısı aldı.",
            $this->logContext()
        );

        $scheduleController = new ScheduleController();

        $type        = 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        $colsPerDay  = ($filters['owner_type'] === 'classroom') ? 1 : 2;
        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

        $row            = $this->writeFileTitle($filters);
        $scheduleFilters = $this->filterBuilder->build($filters);

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            $weekCount   = 1;
            $maxDayIndex = getSettingValue('maxDayIndex', 'lesson', 4);
            $scheduleRows = $scheduleController->prepareScheduleRows($schedule, $maxDayIndex);

            foreach ($scheduleRows as $weekIndex => $slots) {
                $isClassroom = ($scheduleFilter['type'] === 'classroom');
                $colsPerDay  = $isClassroom ? 1 : 2;
                $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
                $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

                if ($weekIndex > 0) {
                    $row += 1;
                    $this->sheet->setCellValue("A{$row}", ($weekIndex + 1) . ". HAFTA");
                    $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $this->sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $row++;
                }

                // Program başlığı (turuncu bar)
                $this->sheet->setCellValue("A{$row}", $scheduleFilter['title']);
                $this->sheet->getStyle("A{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setSize(11);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ffbf00');

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
                        $this->sheet->setCellValue("{$sCol}{$row}", "S");
                        $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                        $this->sheet->getColumnDimension($sCol)->setWidth(8);
                    }
                }

                $this->sheet->getColumnDimension('A')->setWidth(12);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $row++;

                // Slot satırları
                $coveredCells = [];
                $totalRows = count($slots);
                
                foreach ($slots as $rowIndex => $slot) {
                    $timeLabel = $slot['slotStartTime']->format('H:i') . " - " . $slot['slotEndTime']->format('H:i');
                    $this->sheet->setCellValue("A{$row}", $timeLabel);
                    $this->sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($i = 0; $i <= $maxDayIndex; $i++) {
                        $colIdx = $i * $colsPerDay + 2;
                        $col    = Coordinate::stringFromColumnIndex($colIdx);
                        $dayKey = 'day' . $i;

                        if (isset($coveredCells[$weekIndex][$rowIndex][$i])) {
                            continue;
                        }

                        if (isset($slot['days'][$dayKey]) && $slot['days'][$dayKey] !== null) {
                            $items = is_array($slot['days'][$dayKey]) ? $slot['days'][$dayKey] : [$slot['days'][$dayKey]];

                            // Rowspan hesapla
                            $rowSpan = 1;
                            $firstItemId = $items[0]->id;
                            for ($j = $rowIndex + 1; $j < $totalRows; $j++) {
                                $nextSlotItem = $slots[$j]['days'][$dayKey] ?? null;
                                if ($nextSlotItem) {
                                    $nextItems = is_array($nextSlotItem) ? $nextSlotItem : [$nextSlotItem];
                                    if ($nextItems[0]->id === $firstItemId) {
                                        $rowSpan++;
                                        $coveredCells[$weekIndex][$j][$i] = true;
                                    } else {
                                        break;
                                    }
                                } else {
                                    break;
                                }
                            }

                            $combinedContent  = new RichText();
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
                            if ($rowSpan > 1) {
                                $this->sheet->mergeCells("{$col}{$row}:{$col}" . ($row + $rowSpan - 1));
                            }

                            if (!$isClassroom) {
                                $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                                $this->sheet->setCellValue("{$sCol}{$row}", $combinedClassroom);
                                $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()
                                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                                    ->setVertical(Alignment::VERTICAL_CENTER)
                                    ->setWrapText(true);
                                    
                                if ($rowSpan > 1) {
                                    $this->sheet->mergeCells("{$sCol}{$row}:{$sCol}" . ($row + $rowSpan - 1));
                                }
                            }
                            
                            // Yükseklik hesaplaması (Merge edilmiş hücrelerde Excel AutoFit çalışmaz)
                            $lines = substr_count($combinedContent->getPlainText(), "\n") + 1;
                            $requiredHeight = $lines * 14; // Satır başı ortalama 14pt
                            $heightPerRow = ceil($requiredHeight / $rowSpan);
                            
                            if ($heightPerRow > 15) {
                                for ($r = 0; $r < $rowSpan; $r++) {
                                    $currentHeight = $this->sheet->getRowDimension($row + $r)->getRowHeight();
                                    if ($currentHeight === -1 || $currentHeight < $heightPerRow) {
                                        $this->sheet->getRowDimension($row + $r)->setRowHeight($heightPerRow);
                                    }
                                }
                            }
                        }

                        if (!isset($coveredCells[$weekIndex][$rowIndex][$i])) {
                            $this->sheet->getStyle("{$col}{$row}")->getAlignment()
                                ->setWrapText(true)
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                                ->setVertical(Alignment::VERTICAL_CENTER);
                        }
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

        $fileTitle   = $scheduleFilters[array_key_last($scheduleFilters)]['file_title'] ?? 'Program';
        $exportFileName = $filters['academic_year'] . " " . $filters['semester'] . " " . $fileTitle . ".xlsx";
        $this->download($exportFileName);
    }

    /**
     * Ders programı item'ini RichText olarak formatlar.
     */
    private function formatItem(
        ScheduleItem $item,
        string $scheduleType,
        array $options,
        RichText &$richContent,
        RichText &$richClassroom,
        bool $addSeparator = false
    ): void {
        $slotDatas = $item->getSlotDatas();

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

            // Hoca Adı
            if ($options['show_lecturer'] && $scheduleType !== 'user' && $data->lecturer) {
                $richContent->createText("\n(" . $data->lecturer->getFullName() . ")");
            }

            // Program / Bölüm Adı
            if ($options['show_program'] && ($scheduleType === 'user' || $scheduleType === 'classroom')) {
                $programNames = [];
                if ($data->lesson->program) {
                    $programNames[] = $data->lesson->program->name . "-" . getClassFromSemesterNo($data->lesson->semester_no);
                }
                if (!empty($data->lesson->childLessons)) {
                    foreach ($data->lesson->childLessons as $child) {
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

            // Derslik
            if ($scheduleType !== 'classroom' && $data->classroom) {
                $richClassroom->createText($data->classroom->name);
            }
        }
    }
}
