<?php

namespace App\Repositories;

use App\Models\ScheduleItem;
use Exception;

/**
 * ScheduleItem Repository
 * 
 * ScheduleItem model için veri erişim katmanı
 */
class ScheduleItemRepository extends BaseRepository
{
    protected string $modelClass = ScheduleItem::class;

    /**
     * Belirli zaman aralığında çakışan item'ları bulur
     * @param int $scheduleId
     * @param int $dayIndex
     * @param int $weekIndex
     * @param string $startTime
     * @param string $endTime
     * @return array
     * @throws Exception
     */
    public function findConflicting(
        int $scheduleId,
        int $dayIndex,
        int $weekIndex,
        string $startTime,
        string $endTime
    ): array {
        // Aynı schedule, aynı gün, aynı hafta
        $items = $this->findBy([
            'schedule_id' => $scheduleId,
            'day_index' => $dayIndex,
            'week_index' => $weekIndex
        ]);

        // Time overlap kontrolü - application level
        // (Veritabanında time comparison zor olduğu için)
        $conflicting = [];
        foreach ($items as $item) {
            if ($this->hasTimeOverlap($startTime, $endTime, $item->start_time, $item->end_time)) {
                $conflicting[] = $item;
            }
        }

        return $conflicting;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder
     * @param string $start1
     * @param string $end1
     * @param string $start2
     * @param string $end2
     * @return bool
     */
    private function hasTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        // Normalize times (HH:MM format)
        $start1 = substr($start1, 0, 5);
        $end1 = substr($end1, 0, 5);
        $start2 = substr($start2, 0, 5);
        $end2 = substr($end2, 0, 5);

        return ($start1 < $end2) && ($start2 < $end1);
    }

    /**
     * Belirli bir schedule'daki tüm group item'ları bulur
     * @param int $scheduleId
     * @param int $dayIndex
     * @param int $weekIndex
     * @return array
     * @throws Exception
     */
    public function findGroupItems(int $scheduleId, int $dayIndex, int $weekIndex): array
    {
        return $this->findBy([
            'schedule_id' => $scheduleId,
            'day_index' => $dayIndex,
            'week_index' => $weekIndex,
            'status' => 'group'
        ]);
    }

    /**
     * Schedule item'larını toplu siler (ORM kullanarak)
     * Model'in delete() metodunu kullanır - beforeDelete hook'ları çalışır
     * @param array $itemIds
     * @return int Silinen kayıt sayısı
     * @throws Exception
     */
    public function deleteBatch(array $itemIds): int
    {
        if (empty($itemIds)) {
            return 0;
        }

        $deleted = 0;
        foreach ($itemIds as $id) {
            $item = $this->find($id);
            if ($item) {
                $item->delete(); // Model'in delete() metodu - transaction içinde, beforeDelete çalışır
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Belirli bir schedule'a ait tüm item'ları siler (ORM kullanarak)
     * @param int $scheduleId
     * @return int Silinen kayıt sayısı
     * @throws Exception
     */
    public function deleteByScheduleId(int $scheduleId): int
    {
        $items = $this->findBy(['schedule_id' => $scheduleId]);

        $deleted = 0;
        foreach ($items as $item) {
            $item->delete();
            $deleted++;
        }

        return $deleted;
    }
}
