<?php

namespace App\Repositories;

use App\Models\Classroom;

class ClassroomRepository extends BaseRepository
{
    protected string $modelClass = Classroom::class;

    /**
     * Derslik detay sayfası için dersliği programları (schedules) ile birlikte getirir.
     *
     * @param int $id Derslik ID'si
     * @return Classroom|null
     * @throws \Exception
     */
    public function findClassroomWithSchedules(int $id): ?Classroom
    {
        /** @var Classroom $model */
        $model = new $this->modelClass;
        return $model->where(['id' => $id])
            ->with(['schedules' => ['with' => ['items']], 'building'])
            ->first();
    }

    /**
     * Belirtilen birime ait toplam derslik sayısını döner.
     *
     * @param int $unitId Birim ID'si
     * @return int
     */
    public function countByUnit(int $unitId): int
    {
        $sql = "SELECT COUNT(c.id) as total 
                FROM classrooms c 
                INNER JOIN buildings b ON c.building_id = b.id 
                WHERE b.unit_id = :unit_id";
        $stmt = \App\Core\Database::getConnection()->prepare($sql);
        $stmt->execute(['unit_id' => $unitId]);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }
}
