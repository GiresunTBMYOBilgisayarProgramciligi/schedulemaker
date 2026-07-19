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
     * @param string $action
     * @param array $criteria
     * @return array
     * @throws \Exception
     */
    public function getAuthorized(string $action = 'view', array $criteria = []): array
    {
        /** @var Classroom $model */
        $model = new $this->modelClass;
        $query = $model->get()->with(['building']);
        if (!empty($criteria)) {
            $query->where($criteria);
        }
        $classrooms = $query->all();
        return array_values(array_filter($classrooms, fn($c) => \App\Core\Gate::check($action, $c)));
    }
}
