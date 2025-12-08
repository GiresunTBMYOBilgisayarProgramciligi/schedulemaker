<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Core\Model;
use Exception;

class Classroom extends Model
{

    public ?int $id = null;
    public ?string $name = null;
    public ?int $class_size = null;
    public ?int $exam_size = null;
    /*
     * Sınıf Türü
     * 1-> Derslik
     * 2-> Bilgisayar laboratuvarı
     * 3-> Uzaktan Eğitim Sınıfı
     * 4-> Karma (Derslik ve Lab)
     */
    public ?string $type = null;

    public array $schedules = [];

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getSchedulesRelation(array $results): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $schedules = (new Schedule())->get()
            ->where([
                'owner_type' => 'classroom',
                'owner_id' => ['in' => $ids]
            ])->all();

        $schedulesGrouped = [];
        foreach ($schedules as $schedule) {
            $schedulesGrouped[$schedule->owner_id][] = $schedule;
        }

        foreach ($results as &$row) {
            $row['schedules'] = $schedulesGrouped[$row['id']] ?? [];
        }
        return $results;
    }
    protected string $table_name = "classrooms";


    /**
     * @return string
     * @throws Exception
     */
    public function getTypeName(): string
    {
        return (new ClassroomController())->getTypeList()[$this->type] ?? "";
    }
}