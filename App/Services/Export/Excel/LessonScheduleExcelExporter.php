<?php

namespace App\Services\Export\Excel;

use App\Core\Gate;
use App\Enums\PermissionType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Middlewares\AuthMiddleware;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;
use App\Helpers\ScheduleViewHelper;

/**
 * Ders programını Excel formatında dışa aktarır.
 */
class LessonScheduleExcelExporter extends BaseGridExcelExporter
{
    /**
     * @param ScheduleExportFilterDTO $filters     Doğrulanmış filtre DTO'su
     * @param ScheduleExportOptionsDTO $showOptions Gösterim seçenekleri DTO'su
     */
    protected function buildSpreadsheet(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): void
    {
        $scheduleFilters = $this->filterBuilder->build($filters);
        $lastFilterKey   = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastFilterKey !== null && isset($scheduleFilters[$lastFilterKey]['file_title']))
            ? $scheduleFilters[$lastFilterKey]['file_title']
            : 'Ders Programı';
        $username        = $this->logContext()['username'] ?? "Misafir";
        $this->logger()->info(
            "{$username} {$fileTitle} Excel çıktısı aldı.",
            $this->logContext()
        );

        $maxDayIndex = getSettingValue('maxDayIndex', 'lesson', 4);
        // Sütun düzeni dosya başlığıyla (writeFileTitle) aynı kuraldan türetilir
        $isClassroom = $this->isClassroomExport($filters);
        $colsPerDay  = $isClassroom ? 1 : 2;
        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);
        $dayTitles   = array_slice(self::DAYS, 0, $maxDayIndex + 1);

        $row = $this->writeFileTitle($filters);

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            $this->writtenScheduleCount++;

            // Staj dersleri özeti (ana ızgarayı şişirmemek için alt tablo olarak yazılır, misafir/public kullanıcılara gösterilmez)
            $internshipSummary = (AuthMiddleware::user() !== null && $showOptions->showInternship)
                ? ScheduleViewHelper::getInternshipSummary($schedule)
                : [];

            // Tercih / uygun değil kayıtları dışa aktarılmaz
            $scheduleRows = ScheduleViewHelper::stripDummyItems(
                ScheduleViewHelper::prepareScheduleRows($schedule, $maxDayIndex)
            );

            foreach ($scheduleRows as $weekIndex => $slots) {
                if ($weekIndex > 0) {
                    $row = $this->writeWeekHeader($weekIndex, $row, $lastCol);
                }

                // Program başlığı (turuncu bar)
                $row = $this->writeTitleBar($scheduleFilter['title'], $row, $lastCol, 'ffbf00');

                $firstCell = "A{$row}";
                $row = $this->writeDayHeaderRow($dayTitles, $row, $lastCol, $colsPerDay, !$isClassroom);

                // Slot satırları
                $coveredCells = [];

                foreach ($slots as $rowIndex => $slot) {
                    $slotStart = $slot['slotStartTime']->format('H:i');

                    // Öğle Arası: 12:00 başlangıçlı slot birleştirilmiş tek satır olarak gösterilir
                    if (ScheduleViewHelper::isLunchSlot($slot)) {
                        $this->writeLunchRow($slot, $row, $lastCol);
                        $row++;
                        continue;
                    }

                    $this->sheet->setCellValue("A{$row}", $slotStart . " - " . $slot['slotEndTime']->format('H:i'));
                    $this->sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($i = 0; $i <= $maxDayIndex; $i++) {
                        if (isset($coveredCells[$rowIndex][$i])) {
                            continue;
                        }

                        $colIdx = $i * $colsPerDay + 2;
                        $col    = Coordinate::stringFromColumnIndex($colIdx);
                        $items  = ScheduleViewHelper::cellItems($slot['days']['day' . $i] ?? null);

                        if (empty($items)) {
                            continue;
                        }

                        $rowSpan = $this->calculateRowSpan($slots, $rowIndex, $i, $coveredCells, stopAtLunch: true);

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

                        $this->writeMergedCell($col, $row, $rowSpan, $combinedContent);
                        if (!$isClassroom) {
                            $this->writeMergedCell(Coordinate::stringFromColumnIndex($colIdx + 1), $row, $rowSpan, $combinedClassroom);
                        }

                        $this->fitRowHeight($row, $rowSpan, $combinedContent->getPlainText());
                    }

                    $row++;
                }

                $this->drawBorders($firstCell, $lastCol, $row - 1);

                // Staj dersleri varsa ve seçeneklerde aktifse alt tablo olarak ekle
                if (!empty($internshipSummary)) {
                    $row = $this->writeInternshipTable($internshipSummary, $row, $lastCol);
                }

                $row += 2;
            }
        }

        $this->autoSizeColumns('A', $lastCol);
    }

    /**
     * Öğle arası satırını (tüm gün sütunları birleştirilmiş) yazar.
     */
    private function writeLunchRow(array $slot, int $row, string $lastCol): void
    {
        $lunchEndTime = (clone $slot['slotEndTime'])->modify('+10 minutes')->format('H:i');
        $this->sheet->setCellValue("A{$row}", $slot['slotStartTime']->format('H:i') . ' - ' . $lunchEndTime);

        // İçerik hücresi: 2. sütundan son sütuna kadar birleştir
        $this->sheet->setCellValue("B{$row}", 'ÖĞLE ARASI');
        $this->sheet->mergeCells("B{$row}:{$lastCol}{$row}");

        // Stil: arka plan rengi + hizalama + bold
        $lunchStyle = "A{$row}:{$lastCol}{$row}";
        $this->sheet->getStyle($lunchStyle)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('B8D4E8'); // Açık mavi tonu
        $this->sheet->getStyle($lunchStyle)->getFont()
            ->setBold(true)
            ->setItalic(true);
        $this->sheet->getStyle($lunchStyle)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A{$row}")->getFont()->setItalic(false);
        $this->sheet->getRowDimension($row)->setRowHeight(20);
    }

    /**
     * Staj derslerini haftalık tablonun altına özet tablo olarak yazar.
     */
    private function writeInternshipTable(
        array $groups,
        int $startRow,
        string $lastCol
    ): int {
        $row = $startRow + 1;

        // Staj Başlık Çubuğu
        $this->sheet->setCellValue("A{$row}", "STAJ / İŞLETMEDE MESLEKİ EĞİTİM BİLGİLERİ");
        $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setSize(10);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        $tableStartCell = "A{$row}";
        $row++;

        if (empty($groups)) {
            return $row;
        }

        $totalCols = Coordinate::columnIndexFromString($lastCol);

        $codeCol = 'A';
        if ($totalCols >= 9) {
            $nameStartCol = 'B';
            $nameEndCol = 'D';
            $groupStartCol = 'E';
            $groupEndCol = 'E';
            $lecturerStartCol = 'F';
            $lecturerEndCol = 'H';
            $slotStartCol = 'I';
            $slotEndCol = $lastCol;
        } elseif ($totalCols >= 6) {
            $nameStartCol = 'B';
            $nameEndCol = 'B';
            $groupStartCol = 'C';
            $groupEndCol = 'C';
            $lecturerStartCol = 'D';
            $lecturerEndCol = 'E';
            $slotStartCol = 'F';
            $slotEndCol = $lastCol;
        } else {
            $nameStartCol = 'B';
            $nameEndCol = 'B';
            $groupStartCol = 'C';
            $groupEndCol = 'C';
            $lecturerStartCol = 'D';
            $lecturerEndCol = 'D';
            $slotStartCol = 'E';
            $slotEndCol = $lastCol;
        }

        // Başlık satırı
        $this->sheet->setCellValue("{$codeCol}{$row}", "Ders Kodu");
        $this->sheet->setCellValue("{$nameStartCol}{$row}", "Ders Adı");
        if ($nameStartCol !== $nameEndCol) {
            $this->sheet->mergeCells("{$nameStartCol}{$row}:{$nameEndCol}{$row}");
        }

        $this->sheet->setCellValue("{$groupStartCol}{$row}", "Grup");
        if ($groupStartCol !== $groupEndCol) {
            $this->sheet->mergeCells("{$groupStartCol}{$row}:{$groupEndCol}{$row}");
        }

        $this->sheet->setCellValue("{$lecturerStartCol}{$row}", "Sorumlu Öğretim Elemanı");
        if ($lecturerStartCol !== $lecturerEndCol) {
            $this->sheet->mergeCells("{$lecturerStartCol}{$row}:{$lecturerEndCol}{$row}");
        }

        $this->sheet->setCellValue("{$slotStartCol}{$row}", "Gün / Saat Aralığı");
        if ($slotStartCol !== $slotEndCol) {
            $this->sheet->mergeCells("{$slotStartCol}{$row}:{$slotEndCol}{$row}");
        }

        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
        $row++;

        // Satırları yaz
        foreach ($groups as $g) {
            $this->sheet->setCellValue("{$codeCol}{$row}", $g['code']);
            $this->sheet->getStyle("{$codeCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $this->sheet->setCellValue("{$nameStartCol}{$row}", $g['name']);
            if ($nameStartCol !== $nameEndCol) {
                $this->sheet->mergeCells("{$nameStartCol}{$row}:{$nameEndCol}{$row}");
            }

            $this->sheet->setCellValue("{$groupStartCol}{$row}", $g['group']);
            if ($groupStartCol !== $groupEndCol) {
                $this->sheet->mergeCells("{$groupStartCol}{$row}:{$groupEndCol}{$row}");
            }
            $this->sheet->getStyle("{$groupStartCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $this->sheet->setCellValue("{$lecturerStartCol}{$row}", $g['lecturer']);
            if ($lecturerStartCol !== $lecturerEndCol) {
                $this->sheet->mergeCells("{$lecturerStartCol}{$row}:{$lecturerEndCol}{$row}");
            }

            $slotsStr = is_array($g['slots'] ?? null) ? implode(', ', array_unique($g['slots'])) : ($g['slots'] ?? '');
            $this->sheet->setCellValue("{$slotStartCol}{$row}", $slotsStr);
            if ($slotStartCol !== $slotEndCol) {
                $this->sheet->mergeCells("{$slotStartCol}{$row}:{$slotEndCol}{$row}");
            }

            $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $this->sheet->getStyle("{$tableStartCell}:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $row;
    }

    /**
     * Ders programı item'ini RichText olarak formatlar.
     */
    private function formatItem(
        ScheduleItem $item,
        string $scheduleType,
        ScheduleExportOptionsDTO $options,
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
            $lessonName = $data->lesson?->getFullName(addGroup: true) ?? '';
            if ($options->showCode && !empty($data->lesson?->code)) {
                $lessonName = "[" . $data->lesson->code . "] " . $lessonName;
            }
            $richContent->createTextRun($lessonName)->getFont()->setBold(true);

            // Hoca Adı
            if ($options->showLecturer && $scheduleType !== 'user' && !empty($data->lecturer)) {
                $richContent->createText("\n(" . $data->lecturer?->getFullName() . ")");
            }

            // Program / Bölüm Adı
            if ($options->showProgram && ($scheduleType === 'user' || $scheduleType === 'classroom') && !empty($data->lesson)) {
                $programNames = [];
                if ($data->lesson->program) {
                    $programNames[] = $data->lesson->program->name . "-" . getClassFromSemesterNo($data->lesson->semester_no);
                }
                if (!empty($data->lesson->childLessons)) {
                    foreach ($data->lesson->childLessons as $child) {
                        if ($child?->program) {
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
            if ($scheduleType !== 'classroom' && !empty($data->classroom?->name)) {
                $richClassroom->createText($data->classroom?->name ?? '');
            }
        }
    }
}
