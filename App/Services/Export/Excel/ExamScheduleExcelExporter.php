<?php

namespace App\Services\Export\Excel;

use App\Controllers\ScheduleController;
use App\Enums\ExamType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
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
        $colsPerDay         = 1;
        $totalCols          = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol            = Coordinate::stringFromColumnIndex($totalCols);

        $startDate = null;
        $settingKey = match ($type) {
            ExamType::MIDTERM->value => 'midterm_start_date',
            ExamType::FINAL->value => 'final_start_date',
            ExamType::MAKEUP->value => 'makeup_start_date',
            default => null
        };
        if ($settingKey) {
            $startDateString = getSettingValue($settingKey, 'exam');
            if ($startDateString) {
                $startDate = new \DateTime($startDateString);
            }
        }

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
            $weekCount   = ($filters['type'] === ExamType::FINAL->value) ? 2 : 1;
            $maxDayIndex = getSettingValue('maxDayIndex', 'exam', 4);
            $scheduleRows = $scheduleController->prepareScheduleRows($schedule, $maxDayIndex);

            foreach ($scheduleRows as $weekIndex => $slots) {
                $isClassroom = ($scheduleFilter['type'] === 'classroom');
                $colsPerDay  = 1;
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
                    
                    $headerTitle = $days[$i];
                    if ($startDate) {
                        $currentDate = (clone $startDate)->modify("+" . ($weekIndex * 7 + $i) . " days");
                        $headerTitle .= "\n" . $currentDate->format('d.m.Y');
                    }
                    
                    $this->sheet->setCellValue("{$col}{$row}", $headerTitle);
                    $this->sheet->getStyle("{$col}{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                }
                
                $this->sheet->getRowDimension($row)->setRowHeight(35);

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

                            $combinedContent   = new RichText();
                            $combinedContent->createText("\n");

                            foreach ($items as $idx => $item) {
                                $this->formatItem(
                                    $item,
                                    $scheduleFilter['type'],
                                    $showOptions,
                                    $combinedContent,
                                    $idx > 0
                                );
                            }

                            $combinedContent->createText("\n");

                            $this->sheet->setCellValue("{$col}{$row}", $combinedContent);
                            if ($rowSpan > 1) {
                                $this->sheet->mergeCells("{$col}{$row}:{$col}" . ($row + $rowSpan - 1));
                            }
                            
                            // Yükseklik hesaplaması (Merge edilmiş hücrelerde Excel AutoFit çalışmaz)
                            $lines = substr_count($combinedContent->getPlainText(), "\n") + 1;
                            $requiredHeight = $lines * 14; // Satır başı ortalama 14pt (2 satır boşluk vs ekleneceği için yeterli)
                            $heightPerRow = ceil($requiredHeight / $rowSpan);
                            
                            // 15pt normal bir satırın yüksekliğidir. İhtiyaç varsa artırırız.
                            if ($heightPerRow > 15) {
                                for ($r = 0; $r < $rowSpan; $r++) {
                                    $currentHeight = $this->sheet->getRowDimension($row + $r)->getRowHeight();
                                    if ($currentHeight === -1 || $currentHeight < $heightPerRow) {
                                        $this->sheet->getRowDimension($row + $r)->setRowHeight($heightPerRow);
                                    }
                                }
                            }
                        }

                        // Eğer rowspan varsa, bu hücreler covered olsa da border ve hizalama için tüm span'i kapsaması lazım.
                        // PhpSpreadsheet merge edilmiş hücrelere stil vermek için ana hücrenin stilini uygular, bu yüzden
                        // burada sadece ana hücrenin stilini (ve wrapText) veriyoruz.
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
        bool $addSeparator = false
    ): void {
        $slotDatas   = $item->getSlotDatas();
        $assignments = $item->detail['assignments'] ?? null; // Program bazlı kayitta dolu olur

        foreach ($slotDatas as $index => $data) {
            if ($addSeparator || $index > 0) {
                $richContent->createText("\n" . str_repeat('═', 20) . "\n");
                $addSeparator = false;
            }

            // Ders Adı
            $lessonName = $data->lesson->getFullName(addGroup: true);
            if ($options['show_code'] && !empty($data->lesson->code)) {
                $lessonName = "[" . $data->lesson->code . "] " . $lessonName;
            }
            $richContent->createTextRun($lessonName)->getFont()->setBold(true);

            // Hoca Adı (Daima dersin asıl hocası)
            if ($options['show_lecturer'] && !empty($data->lesson->lecturer_id)) {
                $lessonLecturer = (new User())
                    ->get()
                    ->where(['id' => $data->lesson->lecturer_id])
                    ->first();
                if ($lessonLecturer) {
                    $richContent->createText("\n(" . $lessonLecturer->getFullName() . ")");
                }
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

            // ── Derslik ve Gözetmen Bilgisi ─────────────────────────────────────
            if ($assignments !== null) {
                // A) Program bazlı kayit: ders veren hoca yukarıda işlendi,
                //    derslik ve gözetmenler detail['assignments']'tan gelir.

                // Gözetmen ve Derslik birleştirilmesi
                if ($options['show_observer'] ?? false) {
                    $assignmentLines = [];
                    foreach ($assignments as $assignment) {
                        $observerName = $assignment['observer_name'] ?? '';
                        $classroomName = $assignment['classroom_name'] ?? '';
                        
                        // İkisi de varsa "Gözetmen - Derslik(Kalın)", sadece derslik varsa "Derslik(Kalın)", sadece gözetmen varsa "Gözetmen"
                        if (!empty($observerName) && !empty($classroomName)) {
                            // Sadece derslik kısmını bold yapmak için RichText kullanılacak ama şu an TextRun objesi oluşturmamız gerek.
                            // PHPSpreadsheet RichText append mantığında createText ve createTextRun kullanılır.
                            // Bu fonksiyon zaten tek bir element içinde olduğundan, richContent'e ekleyeceğiz.
                            $richContent->createText("\n{$observerName} - ");
                            $richContent->createTextRun($classroomName)->getFont()->setBold(true);
                        } elseif (!empty($classroomName)) {
                            $richContent->createText("\n");
                            $richContent->createTextRun($classroomName)->getFont()->setBold(true);
                        } elseif (!empty($observerName)) {
                            $richContent->createText("\n{$observerName}");
                        }
                    }
                } else {
                    // Sadece derslik isimlerini virgülle ayırarak yazdırıyoruz (Gözetmen seçili değilse)
                    $classroomLines = [];
                    foreach ($assignments as $assignment) {
                        if (!empty($assignment['classroom_name'])) {
                            $classroomLines[] = $assignment['classroom_name'];
                        }
                    }
                    if (!empty($classroomLines)) {
                        $richContent->createText("\n");
                        $richContent->createTextRun(implode(', ', array_unique($classroomLines)))->getFont()->setBold(true);
                    }
                }
            } else {
                // B) Gözetmen/Derslik bazlı kayit: data içinde classifier bilgisi var
                if ($scheduleType !== 'classroom' && $data->classroom) {
                    $richContent->createText("\n");
                    $richContent->createTextRun($data->classroom->name)->getFont()->setBold(true);
                }
                
                // Derslik programında gözetmen gösterilmesi istenmişse (gözetmen data->lecturer içindedir)
                if ($scheduleType === 'classroom' && ($options['show_observer'] ?? false) && $data->lecturer) {
                    $richContent->createText("\n" . $data->lecturer->getFullName());
                }
            }
        }
    }
}
