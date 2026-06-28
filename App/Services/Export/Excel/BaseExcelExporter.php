<?php

namespace App\Services\Export\Excel;

use App\Core\Log;
use App\Enums\ExamType;
use App\Services\Export\ScheduleExporterInterface;
use App\Services\Export\ScheduleExportFilterBuilder;
use Exception;
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
     * Belge üst başlığını yazar (üniversite adı + dönem satırı).
     * @return int Başlık sonrası ilk boş satır numarası
     */
    protected function writeFileTitle(array $filters): int
    {
        $scheduleType = ExamType::isExamType($filters['type'] ?? '') ? 'exam' : 'lesson';
        $maxDayIndex  = getSettingValue('maxDayIndex', $scheduleType, 4);
        $colsPerDay   = ($scheduleType === 'exam' || $filters['owner_type'] === 'classroom') ? 1 : 2;
        $totalCols    = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol      = Coordinate::stringFromColumnIndex($totalCols);

        $this->sheet->setCellValue('A2', 'GİRESUN ÜNİVERSİTESİ TİREBOLU MEHMET BAYRAK MESLEK YÜKSEKOKULU');
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
