<?php

namespace App\Services\Export\Excel;

use App\Core\Log;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Models\Classroom;
use App\Models\Unit;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Repositories\BuildingRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Export\ScheduleExporterInterface;
use App\Services\Export\ScheduleExportFilterBuilder;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use function App\Helpers\getSettingValue;

/**
 * Excel dışa aktarma sınıfları için ortak altyapı:
 * - Spreadsheet/sheet yönetimi
 * - Dosya başlığı yazma
 * - Gün başlığı yazma
 * - Kenarlık uygulama
 * - Dosyayı tarayıcıya gönderme
 */
abstract class BaseExcelExporter implements ScheduleExporterInterface
{
    protected Spreadsheet $spreadsheet;
    protected Worksheet $sheet;
    protected ScheduleExportFilterBuilder $filterBuilder;

    public function __construct()
    {
        $this->spreadsheet   = new Spreadsheet();
        $this->sheet         = $this->spreadsheet->getActiveSheet();
        $this->filterBuilder = new ScheduleExportFilterBuilder();

        // Varsayılan font
        $this->spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(10);
    }

    protected function logger(): Logger
    {
        return Log::logger();
    }

    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    /**
     * Filtre bilgilerinden veya sistemdeki aktif birimlerden birim adını çözümler.
     */
    protected function resolveUnitName(array $filters): string
    {
        $ownerType = $filters['owner_type'] ?? '';
        $ownerId   = !empty($filters['owner_id']) ? (int) $filters['owner_id'] : null;

        if (!empty($filters['unit_id'])) {
            /** @var Unit|null $unit */
            $unit = (new UnitRepository())->find((int) $filters['unit_id']);
            return $unit?->name ?? '';
        }

        if ($ownerId && $ownerType) {
            $unitId = match ($ownerType) {
                'unit', 'classroom_unit' => $ownerId,
                'department' => (new DepartmentRepository())->find($ownerId)?->unit_id,
                OwnerType::PROGRAM->value => (new DepartmentRepository())->find((new ProgramRepository())->find($ownerId)?->department_id)?->unit_id,
                OwnerType::USER->value => (new UserRepository())->find($ownerId)?->unit_id ?? (new DepartmentRepository())->find((new UserRepository())->find($ownerId)?->department_id)?->unit_id,
                OwnerType::CLASSROOM->value => ((new ClassroomRepository())->find($ownerId) instanceof \App\Models\Classroom) ? (new ClassroomRepository())->find($ownerId)->getUnit()?->id : null,
                'building' => (new BuildingRepository())->find($ownerId)?->unit_id,
                OwnerType::LESSON->value => (new DepartmentRepository())->find((new LessonRepository())->find($ownerId)?->department_id)?->unit_id,
                default => null,
            };

            if ($unitId) {
                /** @var Unit|null $unit */
                $unit = (new UnitRepository())->find($unitId);
                if ($unit) {
                    return $unit->name;
                }
            }
        }

        $activeUnits = (new UnitRepository())->getActiveUnits();
        return !empty($activeUnits) ? $activeUnits[0]->name : '';
    }

    /**
     * Excel başlık satırlarını yazar (Üniversite / Birim / Dönem bilgisi).
     *
     * @param array $filters
     * @return int Verilerin başlayacağı bir sonraki satır numarası
     */
    protected function writeFileTitle(array $filters): int
    {
        $type        = $filters['type'] ?? 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        $colsPerDay  = ($filters['owner_type'] === OwnerType::CLASSROOM->value) ? 1 : 2;
        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

        $universityName = getSettingValue('university_name', 'general', 'Giresun Üniversitesi');
        $unitName       = $this->resolveUnitName($filters);
        $periodLabel    = $this->getPeriodLabel($type);

        $academicYear = $filters['academic_year'] ?? getSettingValue('academic_year');
        $semester     = $filters['semester'] ?? getSettingValue('semester');

        $upperUniversity = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $universityName), 'UTF-8');
        $upperUnit = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $unitName), 'UTF-8');
        $titleLine1 = trim("{$upperUniversity} {$upperUnit}");
        $titleLine2 = trim("{$academicYear} {$semester} YARIYILI {$periodLabel}");

        $this->sheet->setCellValue("A2", $titleLine1);
        $this->sheet->mergeCells("A2:{$lastCol}2");
        $this->sheet->getStyle("A2")->getFont()->setBold(true)->setSize(11);
        $this->sheet->getStyle("A2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->setCellValue("A3", $titleLine2);
        $this->sheet->mergeCells("A3:{$lastCol}3");
        $this->sheet->getStyle("A3")->getFont()->setBold(true)->setSize(11);
        $this->sheet->getStyle("A3")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return 5;
    }

    /**
     * Program tipine göre dönem başlığı metnini döndürür.
     */
    protected function getPeriodLabel(string $type): string
    {
        return match ($type) {
            ExamType::MIDTERM->value => 'ARA SINAV PROGRAMI',
            ExamType::FINAL->value   => 'FİNAL SINAV PROGRAMI',
            ExamType::MAKEUP->value  => 'BÜTÜNLEME SINAV PROGRAMI',
            default                  => 'HAFTALIK DERS PROGRAMI',
        };
    }

    /**
     * Spreadsheet içeriğini derler.
     */
    abstract protected function buildSpreadsheet(array $filters, array $showOptions): void;

    /**
     * Dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterArr = $filters instanceof ScheduleExportFilterDTO ? $filters->toArray() : $filters;
        $scheduleFilters = $this->filterBuilder->build($filterArr);
        $lastKey = !empty($scheduleFilters) && is_array($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title'])) ? $scheduleFilters[$lastKey]['file_title'] : 'Program';
        $academicYear = $filterArr['academic_year'] ?? '';
        $semester = $filterArr['semester'] ?? '';
        $baseName = $academicYear . "-" . $semester . "-" . $fileTitle;
        
        return $this->slugify($baseName) . ".xlsx";
    }

    /**
     * Basit slug üretici (dosya adı için)
     */
    protected function slugify(string $text): string
    {
        $turkish = ['ı', 'ğ', 'ü', 'ş', 'i', 'ö', 'ç', 'I', 'Ğ', 'Ü', 'Ş', 'İ', 'Ö', 'Ç'];
        $english = ['i', 'g', 'u', 's', 'i', 'o', 'c', 'i', 'g', 'u', 's', 'i', 'o', 'c'];
        $text = str_replace($turkish, $english, mb_strtolower($text, 'UTF-8'));
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }

    /**
     * Dosyayı tarayıcıya indirme olarak gönderir.
     */
    #[NoReturn]
    public function export(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): void
    {
        $filterArr = $filters instanceof ScheduleExportFilterDTO ? $filters->toArray() : $filters;
        $optionsArr = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions->toArray() : $showOptions;

        $this->buildSpreadsheet($filterArr, $optionsArr);
        $this->download($this->getFileName($filters));
    }

    /**
     * Dışa aktarılan Excel dosyasının ham binary içeriğini string olarak döndürür.
     */
    public function getRawContent(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): string
    {
        $filterArr = $filters instanceof ScheduleExportFilterDTO ? $filters->toArray() : $filters;
        $optionsArr = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions->toArray() : $showOptions;

        $this->buildSpreadsheet($filterArr, $optionsArr);
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    /**
     * Dosyayı tarayıcıya indirme olarak gönderir.
     */
    #[NoReturn]
    protected function download(string $fileName): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * Sütun genişliklerini otomatik ayarlar (manuel set edilmemiş olanlar için).
     */
    protected function autoSizeColumns(string $firstCol, string $lastCol): void
    {
        foreach ($this->sheet->getColumnIterator($firstCol, $lastCol) as $column) {
            $colIdx = $column->getColumnIndex();
            if ($this->sheet->getColumnDimension($colIdx)->getWidth() <= 0) {
                $this->sheet->getColumnDimension($colIdx)->setAutoSize(true);
            }
        }
    }
}
