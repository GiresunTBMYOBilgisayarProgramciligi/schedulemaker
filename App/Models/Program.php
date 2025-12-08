<?php

namespace App\Models;

use App\Core\Model;
use Exception;
use PDO;

class Program extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $department_id = null;
    public ?bool $active = null;

    public ?Department $department = null;
    public array $users = [];
    public array $lessons = [];
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
                'owner_type' => 'program',
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

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getDepartmentRelation(array $results): array
    {
        $deptIds = array_unique(array_column($results, 'department_id'));
        if (empty($deptIds))
            return $results;

        $departments = (new Department())->get()->where(['id' => ['in' => $deptIds]])->all();
        $departmentsKeyed = [];
        foreach ($departments as $dept) {
            $departmentsKeyed[$dept->id] = $dept;
        }

        foreach ($results as &$row) {
            if (isset($row['department_id']) && isset($departmentsKeyed[$row['department_id']])) {
                $row['department'] = $departmentsKeyed[$row['department_id']];
            } else {
                $row['department'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getUsersRelation(array $results): array
    {
        $progIds = array_column($results, 'id');
        if (empty($progIds))
            return $results;

        $users = (new User())->get()->where(['program_id' => ['in' => $progIds]])->all();
        $usersGrouped = [];
        foreach ($users as $user) {
            $usersGrouped[$user->program_id][] = $user;
        }

        foreach ($results as &$row) {
            $row['users'] = $usersGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getLessonsRelation(array $results): array
    {
        $progIds = array_column($results, 'id');
        if (empty($progIds))
            return $results;

        $lessons = (new Lesson())->get()->where(['program_id' => ['in' => $progIds]])->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->program_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['lessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    protected string $table_name = "programs";


    /**
     * @return Department|null
     * @throws Exception
     */
    public function getDepartment(): Department|null
    {
        return (new Department)->find($this->department_id);

    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
        $userModel = new User();
        return $userModel->get()->where(['program_id' => $this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        $userModel = new User();
        return $userModel->get()->where(['program_id' => $this->id])->count();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->count();
    }

    /**
     * Program bünyesindeki derslerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getStudentCount(): mixed
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->sum("size");
    }
}