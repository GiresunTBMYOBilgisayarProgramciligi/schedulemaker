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
