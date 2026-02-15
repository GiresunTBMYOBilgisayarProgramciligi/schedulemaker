<?php

namespace App\Repositories;

use App\Models\Schedule;
use Exception;

/**
 * Schedule Repository
 * 
 * Schedule model için veri erişim katmanı
 */
class ScheduleRepository extends BaseRepository
{
    protected string $modelClass = Schedule::class;

    /**
     * Schedule bulur veya oluşturur (firstOrCreate pattern)
     * @param array $criteria
     * @return Schedule
     * @throws Exception
     */
    public function findOrCreate(array $criteria): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = new $this->modelClass;
        return $schedule->firstOrCreate($criteria);
    }

    /**
     * Belirli owner'a ait schedule'ları bulur
     * @param string $ownerType
     * @param int $ownerId
     * @param string $semester
     * @param string $academicYear
     * @param string $type
     * @return array
     * @throws Exception
     */
    public function findByOwner(
        string $ownerType,
        int $ownerId,
        string $semester,
        string $academicYear,
        string $type
    ): array {
        return $this->findBy([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'type' => $type
        ]);
    }

    /**
     * Birden fazla owner için schedule'ları batch olarak bulur (optimizasyon)
     * @param array $ownerCriteria [['owner_type' => 'user', 'owner_id' => 1], ...]
     * @param string $semester
     * @param string $academicYear
     * @param string $type
     * @return array Schedule'lar, owner_type_id key'li array
     * @throws Exception
     */
    public function findMultipleByOwners(
        array $ownerCriteria,
        string $semester,
        string $academicYear,
        string $type
    ): array {
        if (empty($ownerCriteria)) {
            return [];
        }

        // Group by owner_type
        $groupedByType = [];
        foreach ($ownerCriteria as $criteria) {
            $ownerType = $criteria['owner_type'];
            $ownerId = $criteria['owner_id'];

            if (!isset($groupedByType[$ownerType])) {
                $groupedByType[$ownerType] = [];
            }
            $groupedByType[$ownerType][] = $ownerId;
        }

        $results = [];

        // Her owner_type için batch query
        foreach ($groupedByType as $ownerType => $ownerIds) {
            /** @var Schedule $model */
            $model = new $this->modelClass;

            $schedules = $model->get()->where([
                'owner_type' => $ownerType,
                'owner_id' => ['in' => $ownerIds],
                'semester' => $semester,
                'academic_year' => $academicYear,
                'type' => $type
            ])->all();

            // Key'leri oluştur: owner_type_id
            foreach ($schedules as $schedule) {
                $key = "{$schedule->owner_type}_{$schedule->owner_id}";
                $results[$key] = $schedule;
            }
        }

        return $results;
    }

    /**
     * Belirtilen owner ve dönem için schedule arar
     * 
     * Schedule'lar owner + dönem + tip kombinasyonu ile unique'tir.
     * Bu metod mevcut schedule'ı kontrol etmek için kullanılır.
     * 
     * **Unique Constraint:**
     * - owner_type + owner_id + academic_year + semester + type (+ semester_no)
     * 
     * **Kullanım Örnekleri:**
     * 
     * 1. Öğretim üyesi ders programı:
     *    findByOwnerAndPeriod('user', 146, '2023-2024', 'Güz', 'lesson')
     * 
     * 2. Program dersi (3. dönem):
     *    findByOwnerAndPeriod('program', 531, '2023-2024', 'Güz', 'lesson', 3)
     * 
     * 3. Derslik sınav programı:
     *    findByOwnerAndPeriod('classroom', 1, '2023-2024', 'Güz', 'final-exam')
     * 
     * @param string $ownerType Owner tipi ('user', 'program', 'lesson', 'classroom')
     * @param int $ownerId Owner ID'si
     * @param string $academicYear Akademik yıl (örn: '2023-2024')
     * @param string $semester Dönem ('Güz', 'Bahar', 'Yaz')
     * @param string $type Schedule tipi ('lesson', 'midterm-exam', 'final-exam', 'makeup-exam')
     * @param int|null $semesterNo Dönem numarası (sadece program schedule'lar için, opsiyonel)
     * @return Schedule|null Bulunan schedule veya null
     */
    public function findByOwnerAndPeriod(
        string $ownerType,
        int $ownerId,
        string $academicYear,
        string $semester,
        string $type,
        ?int $semesterNo = null
    ): ?Schedule {
        $conditions = [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'type' => $type
        ];

        // Program schedule'lar için semester_no da kontrol et
        if ($semesterNo !== null) {
            $conditions['semester_no'] = $semesterNo;
        }

        return $this->model->where($conditions)->first();
    }
}
