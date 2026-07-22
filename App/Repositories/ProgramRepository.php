<?php

namespace App\Repositories;

use App\Models\Program;
use Exception;

class ProgramRepository extends BaseRepository
{
    protected string $modelClass = Program::class;

    /**
     * Ada göre program bulur.
     * 
     * @param string $name
     * @return Program|null
     * @throws Exception
     */
    public function findByName(string $name): ?Program
    {
        return $this->findOneBy(["name" => $name]);
    }

    /**
     * Sadece aktif (active=1) programları ilişkileriyle birlikte getirir.
     *
     * @return Program[]
     * @throws Exception
     */
    public function getActiveProgramsWithDetails(): array
    {
        /** @var Program $model */
        $model = new $this->modelClass;
        return $model->get()->where(['active' => true])
            ->with(['lecturers', 'lessons', 'department' => ['with' => ['chairperson', 'unit']]])
            ->all();
    }

    /**
     * Program listesi için tüm programları bölüm bilgisiyle getirir.
     *
     * @return Program[]
     * @throws Exception
     */
    public function getAllProgramsWithDepartment(): array
    {
        /** @var Program $model */
        $model = new $this->modelClass;
        return $model->get()->with(['department'])->all();
    }

    /**
     * Program detay sayfası için programı ilişkileriyle getirir.
     *
     * @param int $id Program ID
     * @return Program|null
     * @throws Exception
     */
    public function findProgramWithDetails(int $id): ?Program
    {
        /** @var Program $model */
        $model = new $this->modelClass;
        return $model->get()->where(['id' => $id])
            ->with([
                'department' => ['with' => ['chairperson']], 
                'lecturers', 
                'lessons' => ['with' => ['lecturer', 'parentLesson' => ['with' => ['program']]]], 
                'schedules' => ['with' => ['items']]
            ])
            ->first();
    }
}
