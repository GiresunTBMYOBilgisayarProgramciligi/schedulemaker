<?php

namespace App\Services\Export\Excel;

use App\Services\Schedule\ExamScheduleService;
use App\Core\Gate;
use App\Enums\PermissionType;
use App\Models\ScheduleItem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;
use App\Helpers\ScheduleViewHelper;

/**
 * Sınav programını (Ara Sınav / Final / Bütünleme) Excel formatında dışa aktarır.
 *
 * Ders programından farkları:
 *  - Final programı 2 haftalık olabilir
 *  - Gözetmen isimleri (show_observer) seçeneği desteklenir
 *  - Gün başlığında sınav tarihi gösterilir
 */
class ExamScheduleExcelExporter extends BaseGridExcelExporter
{
    /**
     * @param ScheduleExportFilterDTO $filters    Doğrulanmış filtre DTO'su
     * @param ScheduleExportOptionsDTO $showOptions Gösterim seçenekleri DTO'su
     */
    protected function buildSpreadsheet(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): void
    {
        $scheduleFilters = $this->filterBuilder->build($filters);
        $lastFilterKey   = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastFilterKey !== null && isset($scheduleFilters[$lastFilterKey]['file_title']))
            ? $scheduleFilters[$lastFilterKey]['file_title']
            : 'Sınav Programı';
        $username        = $this->logContext()['username'] ?? "Sistem";
        $type            = $filters->type ?? 'exam';
        $this->logger()->info(
            "{$username} {$fileTitle} Excel çıktısı aldı.",
            $this->logContext()
        );

        $maxDayIndex = getSettingValue('maxDayIndex', 'exam', 4);
        $colsPerDay  = 1;
        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);
        $startDate   = $this->resolveStartDate($type);

        $row = $this->writeFileTitle($filters);

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new ExamScheduleService())->findScheduleWithItems($scheduleFilter['filter']);

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            $this->writtenScheduleCount++;

            // Tercih / uygun değil kayıtları dışa aktarılmaz. Rowspan hesabı da
            // temizlenmiş satırlar üzerinden yapıldığı için birleştirme doğru olur.
            $scheduleRows = ScheduleViewHelper::stripDummyItems(
                ScheduleViewHelper::prepareScheduleRows($schedule, $maxDayIndex)
            );
            $weekCount = count($scheduleRows);

            foreach ($scheduleRows as $weekIndex => $slots) {
                // Hafta numarası başlığı (birden fazla hafta varsa)
                if ($weekIndex > 0) {
                    $row = $this->writeWeekHeader($weekIndex, $row, $lastCol);
                }

                // Program başlığı (mavi bar — ders programından ayırt etmek için)
                $titleText = $scheduleFilter['title'] . ($weekCount > 1 ? " (" . ($weekIndex + 1) . ". Hafta)" : "");
                $row = $this->writeTitleBar($titleText, $row, $lastCol, '4472C4', 'FFFFFF');

                // Gün başlıkları (başlangıç tarihi tanımlıysa tarih ile)
                $dayTitles = [];
                for ($i = 0; $i <= $maxDayIndex; $i++) {
                    $dayTitles[$i] = self::DAYS[$i];
                    if ($startDate) {
                        $currentDate = (clone $startDate)->modify("+" . ($weekIndex * 7 + $i) . " days");
                        $dayTitles[$i] .= "\n" . $currentDate->format('d.m.Y');
                    }
                }

                $firstCell = "A{$row}";
                $this->sheet->getRowDimension($row)->setRowHeight(35);
                $row = $this->writeDayHeaderRow($dayTitles, $row, $lastCol, $colsPerDay, false);

                // Slot satırları
                $coveredCells = [];

                foreach ($slots as $rowIndex => $slot) {
                    $timeLabel = $slot['slotStartTime']->format('H:i') . " - " . $slot['slotEndTime']->format('H:i');
                    $this->sheet->setCellValue("A{$row}", $timeLabel);
                    $this->sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    for ($i = 0; $i <= $maxDayIndex; $i++) {
                        if (isset($coveredCells[$rowIndex][$i])) {
                            continue;
                        }

                        $col   = Coordinate::stringFromColumnIndex($i * $colsPerDay + 2);
                        $items = ScheduleViewHelper::cellItems($slot['days']['day' . $i] ?? null);

                        if (empty($items)) {
                            continue;
                        }

                        $rowSpan = $this->calculateRowSpan($slots, $rowIndex, $i, $coveredCells);

                        $combinedContent = new RichText();
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

                        $this->writeMergedCell($col, $row, $rowSpan, $combinedContent);
                        $this->fitRowHeight($row, $rowSpan, $combinedContent->getPlainText());
                    }

                    $row++;
                }

                $this->drawBorders($firstCell, $lastCol, $row - 1);
                $row += 2;
            }
        }

        $this->autoSizeColumns('A', $lastCol);
    }

    /**
     * Sınav programı item'ini RichText olarak formatlar.
     *
     * @param ScheduleItem $item
     * @param string $scheduleType 'program'|'user'|'classroom'
     * @param ScheduleExportOptionsDTO $options
     * @param RichText $richContent
     * @param bool $addSeparator
     *
     * Item içindeki yapılar:
     *   1) Program bazlı (normal) item:
     *      - data: [{lesson_id, lecturer_id: X, classroom_id: null}]
     *      - detail: {assignments: [{classroom_id, observer_id, classroom_name, observer_name}]}
     *   2) Hoca bazlı item:
     *      - data: [{lesson_id, lecturer_id: X, classroom_id: null}]
     *      - detail: {assignments: [...]}  (eğer dersin asıl hocası ise)
     *      - data: [{lesson_id, lecturer_id: Y (gözetmen), classroom_id: Z}] (gözetmenlik ise)
     *   3) Derslik bazlı item:
     *      - data: [{lesson_id, lecturer_id: Y, classroom_id: Z}]
     *      - detail: {program_item_id: ..., reference_type: 'exam_assignment'}
     */
    private function formatItem(
        ScheduleItem $item,
        string $scheduleType,
        ScheduleExportOptionsDTO $options,
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
            $groupLabel = $item->getGroupLabel($data->lesson);
            $lessonName = $data->lesson->getFullName() . ($groupLabel !== null ? " ({$groupLabel})" : '');
            if ($options->showCode && !empty($data->lesson->code)) {
                $lessonName = "[" . $data->lesson->code . "] " . $lessonName;
            }
            $richContent->createTextRun($lessonName)->getFont()->setBold(true);

            // Hoca Adı (Daima dersin asıl hocası)
            // Gözetmenlik kayıtlarında ders hocası detail['lesson_lecturer'] içinde gelir
            $lessonLecturerName = $this->resolveExamLessonLecturerName($item, $data);
            if ($options->showLecturer && $lessonLecturerName) {
                // Gözetmenlik kaydında ders hocası ve salon karışmasın diye etiketlenir
                $richContent->createText("\n" . (isset($item->detail['lesson_lecturer']) ? "Hoca: " : "(") . $lessonLecturerName . (isset($item->detail['lesson_lecturer']) ? "" : ")"));
            }


            // Program / Bölüm Adı
            if ($options->showProgram) {
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
                // Programlar alt alta yazılır
                foreach (array_unique($programNames) as $programName) {
                    $richContent->createText("\n(" . $programName . ")");
                }
            }

            // ── Derslik ve Gözetmen Bilgisi ─────────────────────────────────────
            if ($assignments !== null) {
                // A) Program bazlı kayit: ders veren hoca yukarıda işlendi,
                //    derslik ve gözetmenler detail['assignments']'tan gelir.

                // Gözetmen ve Derslik birleştirilmesi
                if ($options->showObserver) {
                    foreach ($assignments as $assignment) {
                        $observerName = '';
                        if (!empty($assignment['observers']) && is_array($assignment['observers'])) {
                            $names = array_filter(array_map(fn($o) => is_array($o) ? ($o['name'] ?? '') : '', $assignment['observers']));
                            $observerName = implode(', ', $names);
                        } elseif (!empty($assignment['observer_name'])) {
                            $observerName = $assignment['observer_name'];
                        }

                        $classroomName = $assignment['classroom_name'] ?? '';
                        
                        // İkisi de varsa "Gözetmen - Derslik(Kalın)", sadece derslik varsa "Derslik(Kalın)", sadece gözetmen varsa "Gözetmen"
                        if (!empty($observerName) && !empty($classroomName)) {
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
                    $richContent->createText(isset($item->detail['lesson_lecturer']) ? "\nSalon: " : "\n");
                    $richContent->createTextRun($data->classroom->name)->getFont()->setBold(true);
                }
                
                // Derslik programında gözetmen gösterilmesi istenmişse (gözetmen data->lecturer içindedir)
                if ($scheduleType === 'classroom' && $options->showObserver && $data->lecturer) {
                    $richContent->createText("\n" . $data->lecturer->getFullName());
                }
            }
        }
    }
}
