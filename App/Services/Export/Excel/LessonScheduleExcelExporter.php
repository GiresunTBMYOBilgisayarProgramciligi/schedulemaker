<?php

namespace App\Services\Export\Excel;

use App\Core\Gate;
use App\Enums\PermissionType;
use App\Enums\ScheduleItemStatus;
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
use App\Enums\LessonType;
use App\Enums\OwnerType;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;
use App\Helpers\ScheduleViewHelper;

/**
 * Ders programını Excel formatında dışa aktarır.
 */
class LessonScheduleExcelExporter extends BaseExcelExporter
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

        $type        = 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        $colsPerDay  = ($filters->owner_type === OwnerType::CLASSROOM->value) ? 1 : 2;
        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

        $row         = $this->writeFileTitle($filters);

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

            // Staj dersleri özeti (ana ızgarayı şişirmemek için alt tablo olarak yazılır, misafir/public kullanıcılara gösterilmez)
            $currentUser = AuthMiddleware::user();
            $canViewInternship = ($currentUser !== null) && (
                Gate::check(PermissionType::UPDATE->value, $schedule) || 
                Gate::check(PermissionType::VIEW->value, $schedule)
            );
            $internshipSummary = ($canViewInternship && $showOptions->showInternship) ? ScheduleViewHelper::getInternshipSummary($schedule) : [];

            $maxDayIndex = getSettingValue('maxDayIndex', 'lesson', 4);
            $scheduleRows = ScheduleViewHelper::prepareScheduleRows($schedule, $maxDayIndex);

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
                    $slotStart = $slot['slotStartTime']->format('H:i');

                    // Öğle Arası: 12:00 başlangıçlı slot birleştirilmiş tek satır olarak gösterilir
                    if ($slotStart === '12:00') {
                        $lunchEndTime = (clone $slot['slotEndTime'])->modify('+10 minutes')->format('H:i');
                        $timeLabel    = $slotStart . ' - ' . $lunchEndTime;

                        $this->sheet->setCellValue("A{$row}", $timeLabel);
                        $this->sheet->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);

                        // İçerik hücresi: 2. sütundan son sütuna kadar birleştir
                        $contentStartCol = Coordinate::stringFromColumnIndex(2);
                        $this->sheet->setCellValue("{$contentStartCol}{$row}", 'ÖĞLE ARASI');
                        $this->sheet->mergeCells("{$contentStartCol}{$row}:{$lastCol}{$row}");

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

                        $row++;
                        continue;
                    }

                    $timeLabel = $slotStart . " - " . $slot['slotEndTime']->format('H:i');
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
                            $cellData = $slot['days'][$dayKey];
                            $items = is_array($cellData) ? $cellData : [$cellData];

                            // Satır birleştirme hesaplama
                            $rowSpan = 1;
                            $firstItem = $items[0];
                            $itemEndTime = $firstItem->getShortEndTime();

                            for ($j = $rowIndex + 1; $j < $totalRows; $j++) {
                                $nextSlot = $slots[$j];
                                $nextSlotStart = $nextSlot['slotStartTime']->format('H:i');

                                if ($nextSlotStart < $itemEndTime) {
                                    $nextCellData = $nextSlot['days'][$dayKey] ?? null;
                                    $isSame = false;
                                    if ($nextCellData) {
                                        $nextItems = is_array($nextCellData) ? $nextCellData : [$nextCellData];
                                        if (count($nextItems) === count($items)) {
                                            $sameCount = 0;
                                            foreach ($items as $it) {
                                                foreach ($nextItems as $nit) {
                                                    if ($it->id === $nit->id) {
                                                        $sameCount++;
                                                        break;
                                                    }
                                                }
                                            }
                                            if ($sameCount === count($items)) {
                                                $isSame = true;
                                            }
                                        }
                                    }

                                    if ($isSame) {
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
                            
                            // Yükseklik hesaplaması
                            $lines = substr_count($combinedContent->getPlainText(), "\n") + 1;
                            $requiredHeight = $lines * 14; 
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

                // Staj dersleri varsa ve seçeneklerde aktifse alt tablo olarak ekle
                if ($showOptions->showInternship && !empty($internshipSummary)) {
                    $row = $this->writeInternshipTable($internshipSummary, $row, $lastCol);
                }

                $row += 2;
            }
        }

        $this->autoSizeColumns('A', $lastCol);
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
