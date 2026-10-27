<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\Core\Gate;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\OwnerType;
use App\Enums\PermissionType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Repositories\ClassroomRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;

/**
 * Ders programını satır bazlı liste formatında Excel olarak dışa aktarır.
 *
 * Sütunlar (gösterim seçeneklerine göre bazıları çıkarılır, bkz. resolveColumns):
 *   Ders Kodu | Ders Adı | Program/Bölüm | Derslik | Hoca | Gün | Başlangıç Saati | Bitiş Saati
 */
class LessonScheduleListExcelExporter extends BaseExcelExporter
{
    /** Ortalanarak yazılan sütunlar; diğerleri sola yaslı ve satır kaydırmalı yazılır */
    protected const CENTERED_COLUMNS = ['code', 'day', 'date', 'start', 'end'];

    protected string $defaultFileTitle = 'Ders Programı';

    /** @var array<string, string> Çıktıdaki sütunlar: anahtar => başlık */
    protected array $columns = [];

    /** Sınav başlangıç tarihi; tanımlıysa "Gün" sütunu yerine "Tarih" sütunu yazılır */
    protected ?\DateTime $startDate = null;

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

        // Sınav başlangıç tarihi tanımlıysa "Gün" yerine sıralanabilir gerçek tarih yazılır
        $this->startDate = $this->resolveStartDate($filters->type ?? 'lesson');

        // Tek tablo: derslik başlık çubukları yazılmaz, derslik adı ilk sütunda yer alır
        $singleTable   = $showOptions->hideTitles && $this->isClassroomExport($filters);
        $this->columns = $this->resolveColumns($filters, $showOptions);
        if ($singleTable) {
            $this->columns = ['owner' => 'Derslik'] + $this->columns;
        }
        $lastCol = Coordinate::stringFromColumnIndex(count($this->columns));

        $this->writeListFileTitle($filters, $lastCol);

        $row = 3;

        // Tek tablo görünümünde tüm dersliklerin satırları toplanıp birlikte sıralanır
        $singleTableEntries = [];

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = $this->findSchedule($scheduleFilter['filter']);

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            // Tercih / uygun değil kayıtları dışa aktarılmaz.
            // Staj dersleri, ızgara çıktısında olduğu gibi yalnızca program programlarında seçeneğe bağlıdır.
            $skipInternship = !$showOptions->showInternship && $scheduleFilter['type'] === OwnerType::PROGRAM->value;
            $items = array_filter(
                $schedule->items,
                fn(ScheduleItem $item) => !$item->isDummy() && !($skipInternship && $item->isInternshipOnly())
            );
            if (empty($items)) {
                continue;
            }

            $this->writtenScheduleCount++;

            if ($singleTable) {
                $ownerName = $this->resolveOwnerName((int) $schedule->owner_id, $scheduleFilter['title']);
                foreach ($items as $item) {
                    $singleTableEntries[] = ['owner' => $ownerName, 'item' => $item, 'type' => $scheduleFilter['type']];
                }
                continue;
            }

            $row          = $this->writeTitleBar($scheduleFilter['title'], $row, $lastCol, 'ffbf00');
            $row          = $this->writeColumnHeaders($row, $lastCol);
            $dataStartRow = $row;

            usort($items, fn(ScheduleItem $a, ScheduleItem $b) => $this->compareItems($a, $b));
            foreach ($items as $item) {
                $row = $this->writeItem($item, $scheduleFilter['type'], $row);
            }

            $this->drawDataBorders($dataStartRow, $row, $lastCol);
            $row++;
        }

        if (!empty($singleTableEntries)) {
            usort($singleTableEntries, fn(array $a, array $b) => $this->compareItems($a['item'], $b['item'])
                ?: strnatcmp($a['owner'], $b['owner']));

            $headerRow    = $row;
            $row          = $this->writeColumnHeaders($row, $lastCol);
            $dataStartRow = $row;
            foreach ($singleTableEntries as $entry) {
                $row = $this->writeItem($entry['item'], $entry['type'], $row, $entry['owner']);
            }
            $this->drawDataBorders($dataStartRow, $row, $lastCol);

            // Hizmetli / görevli listesi olarak kullanılırken gün veya dersliğe göre süzülebilsin
            $this->sheet->setAutoFilter("A{$headerRow}:{$lastCol}" . ($row - 1));
        }

        $this->autoSizeColumns('A', $lastCol);
    }

    /**
     * Item'ın tarihini Excel tarih değeri olarak yazar; böylece Excel'de tarihe göre doğru sıralanır.
     * Görünüm Türkçe gün adıyla birlikte "27.10.2026 Salı" biçimindedir.
     */
    private function writeDateCell(string $cell, ScheduleItem $item): void
    {
        $date = (clone $this->startDate)->modify('+' . ((int) $item->week_index * 7 + (int) $item->day_index) . ' days');
        $this->sheet->setCellValue($cell, ExcelDate::PHPToExcel($date));
        $this->sheet->getStyle($cell)->getNumberFormat()->setFormatCode('[$-41F]dd.mm.yyyy dddd');
    }

    /**
     * Item'ları zaman sırasına göre karşılaştırır: hafta, gün, başlangıç saati.
     */
    private function compareItems(ScheduleItem $a, ScheduleItem $b): int
    {
        return [(int) $a->week_index, (int) $a->day_index, (string) $a->start_time]
            <=> [(int) $b->week_index, (int) $b->day_index, (string) $b->start_time];
    }

    /**
     * Veri satırlarına ($startRow .. $endRow - 1) kenarlık çizer.
     */
    private function drawDataBorders(int $startRow, int $endRow, string $lastCol): void
    {
        if ($endRow > $startRow) {
            $this->drawBorders("A{$startRow}", $lastCol, $endRow - 1);
        }
    }

    /**
     * Tek tablo görünümünde "Derslik" sütununa yazılacak program sahibinin (derslik) adı.
     */
    private function resolveOwnerName(int $classroomId, string $fallback): string
    {
        return (new ClassroomRepository())->find($classroomId)?->name ?? $fallback;
    }

    /**
     * Gösterim seçeneklerine ve çıktı türüne göre sütunları belirler (alt sınıflar ezebilir).
     *
     * - Ders Kodu / Program/Bölüm / Hoca: ilgili seçenek kapalıysa çıkarılır
     * - Derslik: derslik bazlı çıktılarda derslik zaten program sahibidir, çıkarılır
     * - Hoca: hoca bazlı ders programında hoca zaten program sahibidir, çıkarılır
     *
     * @return array<string, string> anahtar => başlık
     */
    protected function resolveColumns(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $options): array
    {
        $columns = [];
        if ($options->showCode) {
            $columns['code'] = 'Ders Kodu';
        }
        $columns['name'] = 'Ders Adı';
        if ($options->showProgram) {
            $columns['program'] = 'Program/Bölüm';
        }
        if (!$this->isClassroomExport($filters)) {
            $columns['classroom'] = 'Derslik';
        }
        if ($options->showLecturer && $filters->owner_type !== OwnerType::USER->value) {
            $columns['lecturer'] = 'Hoca';
        }
        if ($this->startDate) {
            $columns['date'] = 'Tarih';
        } else {
            $columns['day'] = 'Gün';
        }
        $columns['start'] = 'Başlangıç Saati';
        $columns['end']   = 'Bitiş Saati';

        return $columns;
    }

    /**
     * Filtreye uyan programı item'larıyla birlikte getirir (alt sınıflar ezebilir).
     */
    protected function findSchedule(array $filter): ?Schedule
    {
        return (new Schedule())->get()->where($filter)->with('items')->first();
    }

    /**
     * Dönem/program başlık etiketini belirler (alt sınıflar ezebilir).
     */
    protected function resolvePeriodLabel(ScheduleExportFilterDTO $filters): string
    {
        return 'HAFTALIK DERS PROGRAMI (LİSTE)';
    }

    /**
     * Liste formatı için dosya başlıklarını ve logoları yazar (satır 1–2).
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

        $totalCols = Coordinate::columnIndexFromString($lastCol);
        $this->headerLastCol = $lastCol;

        $this->renderHeaderRow($titleLine1, $titleLine2, $lastCol, $totalCols);
    }

    /**
     * Kolon başlıklarını yazar.
     */
    protected function writeColumnHeaders(int $row, string $lastCol): int
    {
        foreach (array_values($this->columns) as $colIndex => $header) {
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
        int $row,
        string $ownerName = ''
    ): int {
        foreach ($item->getSlotDatas() as $data) {
            $lineCount = 1;
            foreach (array_keys($this->columns) as $colIndex => $key) {
                $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . $row;

                if ($key === 'date') {
                    $this->writeDateCell($cell, $item);
                    $value = '';
                } else {
                    // Yalnızca çıktıda yer alan sütunların değeri hesaplanır (hoca adı sorgu gerektirebilir)
                    $value = match ($key) {
                        'owner'     => $ownerName,
                        'code'      => $data->lesson?->code ?? '',
                        'name'      => $this->resolveLessonName($item, $data),
                        'program'   => $this->resolveProgramName($data),
                        'classroom' => $this->resolveClassroomName($item, $data, $scheduleType),
                        'lecturer'  => $this->resolveLecturerName($item, $data, $scheduleType),
                        'observer'  => $this->resolveObserverName($item, $data, $scheduleType),
                        'day'       => self::DAYS[$item->day_index] ?? '',
                        'start'     => $item->getShortStartTime(),
                        'end'       => $item->getShortEndTime(),
                        default     => '',
                    };
                    // Metin olarak yazılır; "=" ile başlayan bir ders adı formül olarak yorumlanmaz
                    $this->sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                }

                $alignment = $this->sheet->getStyle($cell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                if (in_array($key, static::CENTERED_COLUMNS, true)) {
                    $alignment->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $alignment->setWrapText(true);
                }

                $lineCount = max($lineCount, substr_count($value, "\n") + 1);
            }

            // Alt alta yazılan program/salon/hoca değerleri için satır yüksekliği
            $this->sheet->getRowDimension($row)->setRowHeight(max(20, $lineCount * 15));

            $row++;
        }

        return $row;
    }

    /**
     * Ders adı (grup harfi dahil).
     */
    protected function resolveLessonName(ScheduleItem $item, object $data): string
    {
        $lesson = $data->lesson;
        if (!$lesson) {
            return '';
        }
        $groupLabel = $item->getGroupLabel($lesson);

        return $lesson->getFullName() . ($groupLabel !== null ? " ({$groupLabel})" : '');
    }

    /**
     * Program/Bölüm hücresi: dersin programı ve bağlı derslerin programları alt alta.
     */
    protected function resolveProgramName(object $data): string
    {
        $lesson = $data->lesson;
        if (!$lesson) {
            return '';
        }
        $classSuffix = $lesson->semester_no ? ' - ' . getClassFromSemesterNo($lesson->semester_no) : '';

        $programNames = [];
        if ($lesson->program) {
            $programNames[] = $lesson->program->name . $classSuffix;
        }
        foreach ($this->resolveChildLessons($lesson) as $child) {
            if ($child?->program) {
                $programNames[] = $child->program->name . $classSuffix;
            }
        }

        return implode("\n", array_unique($programNames));
    }

    /**
     * Derslik hücresi değerini belirler (derslik tabanlı programda derslik zaten schedule sahibidir).
     */
    protected function resolveClassroomName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        if ($scheduleType !== 'classroom' && !empty($data->classroom)) {
            return $data->classroom->name;
        }

        return '';
    }

    /**
     * Program sütununda gösterilecek bağlı dersleri belirler (alt sınıflar ezebilir).
     *
     * @return \App\Models\Lesson[]
     */
    protected function resolveChildLessons(\App\Models\Lesson $lesson): array
    {
        return $lesson->childLessons ?? [];
    }

    /**
     * Hoca hücresi değerini belirler (hoca tabanlı programda hoca zaten schedule sahibidir).
     */
    protected function resolveLecturerName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        if ($scheduleType !== 'user' && !empty($data->lecturer)) {
            return $data->lecturer->getFullName();
        }

        return '';
    }

    /**
     * Gözetmen hücresi değerini belirler. Ders programında gözetmen yoktur (alt sınıflar ezebilir).
     */
    protected function resolveObserverName(ScheduleItem $item, object $data, string $scheduleType): string
    {
        return '';
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
