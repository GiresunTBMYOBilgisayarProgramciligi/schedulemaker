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
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
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
    protected string $headerLastCol = '';

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
    protected function resolveUnitName(ScheduleExportFilterDTO|array $filters): string
    {
        $ownerType = $filters instanceof ScheduleExportFilterDTO ? ($filters->owner_type ?? '') : ($filters['owner_type'] ?? '');
        $ownerId   = $filters instanceof ScheduleExportFilterDTO ? $filters->owner_id : (!empty($filters['owner_id']) ? (int) $filters['owner_id'] : null);
        $unitIdFilter = $filters instanceof ScheduleExportFilterDTO ? null : (!empty($filters['unit_id']) ? (int) $filters['unit_id'] : null);

        if (!empty($unitIdFilter)) {
            /** @var Unit|null $unit */
            $unit = (new UnitRepository())->find($unitIdFilter);
            return $unit?->name ?? '';
        }

        if ($ownerId && $ownerType) {
            $unitId = match ($ownerType) {
                'unit', 'classroom_unit' => $ownerId,
                'department' => (new DepartmentRepository())->find($ownerId)?->unit_id,
                OwnerType::PROGRAM->value => (new DepartmentRepository())->find((new ProgramRepository())->find($ownerId)?->department_id)?->unit_id,
                OwnerType::USER->value => (new UserRepository())->find($ownerId)?->unit_id ?? (new DepartmentRepository())->find((new UserRepository())->find($ownerId)?->department_id)?->unit_id,
                OwnerType::CLASSROOM->value => ((new ClassroomRepository())->find($ownerId) instanceof Classroom) ? (new ClassroomRepository())->find($ownerId)->getUnit()?->id : null,
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
     * Excel başlık satırlarını yazar (Üniversite / Birim / Dönem bilgisi ve iki yanda logo).
     *
     * @param ScheduleExportFilterDTO|array $filters
     * @return int Verilerin başlayacağı bir sonraki satır numarası
     */
    protected function writeFileTitle(ScheduleExportFilterDTO|array $filters): int
    {
        $filtersDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $type        = $filtersDTO->type ?? 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $isExam      = in_array($type, ['exam', ExamType::MIDTERM->value, ExamType::FINAL->value, ExamType::MAKEUP->value], true);
        $isClassroom = in_array($filtersDTO->owner_type, [OwnerType::CLASSROOM->value, 'classroom_unit', 'building'], true);
        $colsPerDay  = ($isExam || $isClassroom) ? 1 : 2;

        $totalCols   = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);
        $this->headerLastCol = $lastCol;

        $universityName = getSettingValue('university_name', 'general', 'Giresun Üniversitesi');
        $unitName       = $this->resolveUnitName($filtersDTO);
        $periodLabel    = $this->getPeriodLabel($type);

        $academicYear = $filtersDTO->academic_year ?? getSettingValue('academic_year');
        $semester     = $filtersDTO->semester ?? getSettingValue('semester');

        $upperUniversity = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $universityName), 'UTF-8');
        $upperUnit       = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $unitName), 'UTF-8');
        $titleLine1      = trim("{$upperUniversity} {$upperUnit}");
        $titleLine2      = trim("{$academicYear} {$semester} YARIYILI {$periodLabel}");

        $this->renderHeaderRow($titleLine1, $titleLine2, $lastCol, $totalCols);

        return 3;
    }

    /**
     * Ortak başlık formatını uygular ve logoları ekler.
     */
    protected function renderHeaderRow(string $titleLine1, string $titleLine2, string $lastCol, int $totalCols): void
    {
        // Başlık satırlarının yükseklikleri (ferah ve logolarla dengeli)
        $this->sheet->getRowDimension(1)->setRowHeight(30);
        $this->sheet->getRowDimension(2)->setRowHeight(30);

        // A1'den son sütunun 2. satırına kadar tek bir büyük hücre olarak birleştir
        $this->sheet->mergeCells("A1:{$lastCol}2");

        // İki satırlı başlık metnini RichText olarak oluştur
        $richText = new RichText();
        $run1 = $richText->createTextRun($titleLine1 . "\n");
        $run1->getFont()->setBold(true)->setSize(13);

        $run2 = $richText->createTextRun($titleLine2);
        $run2->getFont()->setBold(true)->setSize(11);

        $this->sheet->setCellValue("A1", $richText);
        $this->sheet->getStyle("A1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // İki yana kurumsal logoları yerleştir
        $this->addHeaderLogos($totalCols);
    }

    /**
     * Başlığın sol ve sağ tarafına kurum logolarını ekler.
     *
     * @param int $totalCols Toplam sütun sayısı
     */
    protected function addHeaderLogos(int $totalCols): void
    {
        $logoPath = dirname(__DIR__, 4) . '/Public/assets/images/gru_official_logo.png';
        if (!file_exists($logoPath)) {
            $logoPath = dirname(__DIR__, 4) . '/Public/assets/images/gru_logo.png';
        }
        if (!file_exists($logoPath)) {
            return;
        }

        // Sol Logo (A1 - Saat sütunu sol kenarından 12px içeride, dikeyde ortalı)
        $leftDrawing = new Drawing();
        $leftDrawing->setName('Kurum Logosu (Sol)');
        $leftDrawing->setDescription('Giresun Üniversitesi Logosu');
        $leftDrawing->setPath($logoPath);
        $leftDrawing->setCoordinates('A1');
        $leftDrawing->setHeight(60);
        $leftDrawing->setOffsetX(12);
        $leftDrawing->setOffsetY(10);
        $leftDrawing->setWorksheet($this->sheet);

        if ($totalCols >= 3) {
            $lastCol = Coordinate::stringFromColumnIndex($totalCols);
            $rightDrawing = new Drawing();
            $rightDrawing->setName('Kurum Logosu (Sağ)');
            $rightDrawing->setDescription('Giresun Üniversitesi Logosu');
            $rightDrawing->setPath($logoPath);
            $rightDrawing->setHeight(60);
            $rightDrawing->setOffsetY(10);
            $rightDrawing->setCoordinates($lastCol . '1');
            $rightDrawing->setOffsetX(0);
            $rightDrawing->setWorksheet($this->sheet);
        }
    }

    /**
     * Tablo hücreleri ve sütun genişlikleri oluştuktan sonra, sağ logoyu
     * tablonun en sağ kenarına tam yaslar (taşma olmadan, simetrik boşlukla).
     */
    protected function alignRightHeaderLogo(): void
    {
        if (empty($this->headerLastCol)) {
            return;
        }

        $rightDrawing = null;
        foreach ($this->sheet->getDrawingCollection() as $drawing) {
            if ($drawing->getName() === 'Kurum Logosu (Sağ)') {
                $rightDrawing = $drawing;
                break;
            }
        }

        if (!$rightDrawing) {
            return;
        }

        $this->sheet->calculateColumnWidths();
        $font = $this->spreadsheet->getDefaultStyle()->getFont();

        $logoWidth = $rightDrawing->getWidth() > 0 ? $rightDrawing->getWidth() : 60;
        $padding = 10; // Sağ sınır ile logo arasındaki boşluk (px)
        $requiredWidth = $logoWidth + $padding;

        $targetColIdx = Coordinate::columnIndexFromString($this->headerLastCol);
        $accumulatedWidth = 0;

        // Son sütundan geriye doğru sütun genişliklerini topla
        while ($targetColIdx >= 1) {
            $colString = Coordinate::stringFromColumnIndex($targetColIdx);
            $colWidth = $this->sheet->getColumnDimension($colString)->getWidth();
            $colPx = SharedDrawing::cellDimensionToPixels($colWidth > 0 ? $colWidth : 10, $font);

            $accumulatedWidth += $colPx;
            if ($accumulatedWidth >= $requiredWidth || $targetColIdx === 1) {
                $offsetX = (int) max(0, $accumulatedWidth - $requiredWidth);
                $rightDrawing->setCoordinates($colString . '1');
                $rightDrawing->setOffsetX($offsetX);
                break;
            }

            $targetColIdx--;
        }
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
    abstract protected function buildSpreadsheet(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): void;

    /**
     * Dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterDTO       = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $scheduleFilters = $this->filterBuilder->build($filterDTO);
        $lastKey         = !empty($scheduleFilters) && is_array($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title'])) ? $scheduleFilters[$lastKey]['file_title'] : 'Program';
        $academicYear    = $filterDTO->academic_year ?? '';
        $semester        = $filterDTO->semester ?? '';
        $baseName        = $academicYear . "-" . $semester . "-" . $fileTitle;
        
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
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $this->buildSpreadsheet($filterDTO, $optionsDTO);
        $this->alignRightHeaderLogo();
        $this->download($this->getFileName($filterDTO));
    }

    /**
     * Dışa aktarılan Excel dosyasının ham binary içeriğini string olarak döndürür.
     */
    public function getRawContent(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): string
    {
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $this->buildSpreadsheet($filterDTO, $optionsDTO);
        $this->alignRightHeaderLogo();
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
