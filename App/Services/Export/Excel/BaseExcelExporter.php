<?php

namespace App\Services\Export\Excel;

use App\Core\Log;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Models\Classroom;
use App\Models\Unit;
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
     * Belge üst başlığını yazar (üniversite adı + birim adı + dönem satırı).
     * @return int Başlık sonrası ilk boş satır numarası
     */
    protected function writeFileTitle(array $filters): int
    {
        $scheduleType = ExamType::isExamType($filters['type'] ?? '') ? 'exam' : 'lesson';
        $maxDayIndex  = getSettingValue('maxDayIndex', $scheduleType, 4);
        $colsPerDay   = ($scheduleType === 'exam' || $filters['owner_type'] === OwnerType::CLASSROOM->value) ? 1 : 2;
        $totalCols    = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol      = Coordinate::stringFromColumnIndex($totalCols);

        $unitName = $this->resolveUnitName($filters);
        $headerTitle = 'GİRESUN ÜNİVERSİTESİ';
        if (!empty($unitName)) {
            $unitNameUpper = strtr($unitName, ['i' => 'İ', 'ı' => 'I', 'ğ' => 'Ğ', 'ü' => 'Ü', 'ş' => 'Ş', 'ö' => 'Ö', 'ç' => 'Ç']);
            $headerTitle .= ' ' . mb_strtoupper($unitNameUpper, 'UTF-8');
        }

        $this->sheet->setCellValue('A2', $headerTitle);
        $this->sheet->mergeCells("A2:{$lastCol}2");
        $this->sheet->getStyle("A2:{$lastCol}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true)->setSize(12);

        $periodLabel = $this->getPeriodLabel($filters['type'] ?? 'lesson');
        $this->sheet->setCellValue('A3', $filters['academic_year'] . ' AKADEMİK YILI ' . mb_strtoupper($filters['semester']) . ' DÖNEMİ ' . $periodLabel);
        $this->sheet->mergeCells("A3:{$lastCol}3");
        $this->sheet->getStyle("A3:{$lastCol}3")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(12);

        return 6;
    }

    /**
     * Program türüne göre belge başlık etiketi
     */
    protected function getPeriodLabel(string $type): string
    {
        return match ($type) {
            ExamType::MIDTERM->value => 'ARA SINAV PROGRAMI',
            ExamType::FINAL->value   => 'FİNAL SINAV PROGRAMI',
            ExamType::MAKEUP->value  => 'BÜTÜNLEME SINAV PROGRAMI',
            default        => 'HAFTALIK DERS PROGRAMI',
        };
    }

    /**
     * Spreadsheet içeriğini derler.
     */
    abstract protected function buildSpreadsheet(array $filters, array $showOptions): void;

    /**
     * Dosya adını üretir.
     */
    public function getFileName(array $filters): string
    {
        $scheduleFilters = $this->filterBuilder->build($filters);
        $fileTitle = $scheduleFilters[array_key_last($scheduleFilters)]['file_title'] ?? 'Program';
        $baseName = $filters['academic_year'] . "-" . $filters['semester'] . "-" . $fileTitle;
        
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
    public function export(array $filters, array $showOptions): void
    {
        $this->buildSpreadsheet($filters, $showOptions);
        $this->download($this->getFileName($filters));
    }

    /**
     * Dışa aktarılan Excel dosyasının ham binary içeriğini string olarak döndürür.
     */
    public function getRawContent(array $filters, array $showOptions): string
    {
        $this->buildSpreadsheet($filters, $showOptions);
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
