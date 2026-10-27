<?php

namespace App\Services\Export\Excel;

use App\Models\ScheduleItem;
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
     * Hücre içeriğini item listesine çevirir.
     *
     * @return ScheduleItem[]
     */
    protected function cellItems(mixed $cellData): array
    {
        if (empty($cellData)) {
            return [];
        }

        return is_array($cellData) ? array_values($cellData) : [$cellData];
    }

    /**
     * Hücredeki item'ların sonraki slotlarda aynen devam ettiği satır sayısını hesaplar
     * ve kapsanan hücreleri $coveredCells içine işaretler.
     *
     * Karşılaştırma tüm item kümesi üzerinden yapılır; hücrede birden fazla item varsa
     * hepsi bir sonraki slotta da bulunmadıkça birleştirme yapılmaz.
     *
     * @param array $slots prepareScheduleRows çıktısındaki tek haftanın slotları
     * @param ScheduleItem[] $items Başlangıç hücresindeki item'lar
     * @param array $coveredCells [rowIndex][dayIndex] => true
     */
    protected function calculateRowSpan(array $slots, int $rowIndex, int $dayIndex, array $items, array &$coveredCells): int
    {
        $dayKey   = 'day' . $dayIndex;
        $itemIds  = $this->itemIdSet($items);
        $rowSpan  = 1;
        $total    = count($slots);

        for ($j = $rowIndex + 1; $j < $total; $j++) {
            $nextItems = $this->cellItems($slots[$j]['days'][$dayKey] ?? null);
            if (empty($nextItems) || $this->itemIdSet($nextItems) !== $itemIds) {
                break;
            }
            $rowSpan++;
            $coveredCells[$j][$dayIndex] = true;
        }

        return $rowSpan;
    }

    /**
     * @param ScheduleItem[] $items
     * @return array Sıralı item id listesi
     */
    private function itemIdSet(array $items): array
    {
        $ids = array_map(fn($item) => $item->id, $items);
        sort($ids);

        return $ids;
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
