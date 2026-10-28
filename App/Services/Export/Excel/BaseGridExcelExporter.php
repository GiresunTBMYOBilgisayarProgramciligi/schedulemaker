<?php

namespace App\Services\Export\Excel;

use App\Helpers\ScheduleViewHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Izgara (Saat × Gün) formatındaki Excel dışa aktarma sınıfları için ortak altyapı:
 * - Hafta / program başlık çubukları
 * - Gün başlık satırı
 * - Ardışık slotlarda aynı item'ların satır birleştirmesi (rowspan)
 * - Birleştirilmiş hücreler için satır yüksekliği
 */
abstract class BaseGridExcelExporter extends BaseExcelExporter
{
    /** Hücre içinde bir metin satırı için ayrılan yükseklik (pt) */
    protected const LINE_HEIGHT = 14;

    /**
     * Birden fazla hafta varsa hafta numarası satırını yazar.
     *
     * @return int Bir sonraki satır numarası
     */
    protected function writeWeekHeader(int $weekIndex, int $row, string $lastCol): int
    {
        $row += 1;
        $this->sheet->setCellValue("A{$row}", ($weekIndex + 1) . ". HAFTA");
        $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $this->sheet->getStyle("A{$row}")->getFont()->setBold(true);

        return $row + 1;
    }

    /**
     * Gün başlık satırını yazar.
     *
     * @param string[] $dayTitles Gün indeksine göre başlık metinleri (0..maxDayIndex)
     * @param bool $withClassroomCol Her günün yanına "S" (derslik) sütunu eklensin mi
     * @return int Bir sonraki satır numarası
     */
    protected function writeDayHeaderRow(array $dayTitles, int $row, string $lastCol, int $colsPerDay, bool $withClassroomCol): int
    {
        $this->sheet->setCellValue("A{$row}", "Saat");

        foreach ($dayTitles as $i => $title) {
            $colIdx = $i * $colsPerDay + 2;
            $col    = Coordinate::stringFromColumnIndex($colIdx);
            $this->sheet->setCellValue("{$col}{$row}", $title);

            if ($withClassroomCol) {
                $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                $this->sheet->setCellValue("{$sCol}{$row}", "S");
                $this->sheet->getColumnDimension($sCol)->setWidth(8);
            }
        }

        $this->sheet->getColumnDimension('A')->setWidth(12);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        return $row + 1;
    }

    /**
     * Ortak birleştirme kuralıyla (ScheduleViewHelper::calculateRowSpan) birleştirilecek satır sayısını
     * döndürür ve kapsanan hücreleri $coveredCells içine işaretler.
     *
     * @param array $slots prepareScheduleRows çıktısındaki tek haftanın slotları
     * @param array $coveredCells [rowIndex][dayIndex] => true
     * @param bool $stopAtLunch Öğle arası ayrı satır olarak yazılıyorsa birleştirme öğle arasında kesilir
     */
    protected function calculateRowSpan(array $slots, int $rowIndex, int $dayIndex, array &$coveredCells, bool $stopAtLunch = false): int
    {
        $rowSpan = ScheduleViewHelper::calculateRowSpan($slots, $rowIndex, 'day' . $dayIndex, $stopAtLunch);
        for ($j = $rowIndex + 1; $j < $rowIndex + $rowSpan; $j++) {
            $coveredCells[$j][$dayIndex] = true;
        }

        return $rowSpan;
    }

    /**
     * Hücreyi yazar, gerekiyorsa dikey olarak birleştirir ve ortalar.
     */
    protected function writeMergedCell(string $col, int $row, int $rowSpan, RichText|string $content): void
    {
        $this->sheet->setCellValue("{$col}{$row}", $content);
        if ($rowSpan > 1) {
            $this->sheet->mergeCells("{$col}{$row}:{$col}" . ($row + $rowSpan - 1));
        }
        $this->sheet->getStyle("{$col}{$row}")->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Birleştirilmiş hücrelerde Excel AutoFit çalışmadığından satır yüksekliklerini
     * içerik satır sayısına göre ayarlar. Gün sütunları AutoSize ile en uzun satıra
     * göre genişlediği için metin kaydırılmaz; satır sayısı "\n" sayısıdır.
     */
    protected function fitRowHeight(int $row, int $rowSpan, string $plainText): void
    {
        $lines        = substr_count($plainText, "\n") + 1;
        $heightPerRow = (int) ceil($lines * self::LINE_HEIGHT / $rowSpan);

        // 15pt normal bir satırın yüksekliğidir. İhtiyaç varsa artırılır.
        if ($heightPerRow <= 15) {
            return;
        }

        for ($r = 0; $r < $rowSpan; $r++) {
            $dimension     = $this->sheet->getRowDimension($row + $r);
            $currentHeight = $dimension->getRowHeight();
            if ($currentHeight === -1 || $currentHeight < $heightPerRow) {
                $dimension->setRowHeight($heightPerRow);
            }
        }
    }
}
