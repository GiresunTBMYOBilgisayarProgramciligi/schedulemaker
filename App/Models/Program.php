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
    public array $lecturers = [];
    public array $lessons = [];
    public array $schedules = [];
    protected array $excludeFromDb = ['department', 'users', 'lecturers', 'lessons', 'schedules'];
    protected string $table_name = "programs";

    public function getLabel(): string
    {
        return "program";
    }

    public function getLogDetail(): string
    {
        return $this->name ?? "ID: " . $this->id;
    }

    public function getSchedulesRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Schedule())->get()
            ->where([
                'owner_type' => 'program',
                'owner_id' => ['in' => $ids]
            ]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $schedules = $query->all();

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
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getDepartmentRelation(array $results, array $options = []): array
    {
        $deptIds = array_unique(array_column($results, 'department_id'));
        if (empty($deptIds))
            return $results;

        $query = (new Department())->get()->where(['id' => ['in' => $deptIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $departments = $query->all();
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
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getUsersRelation(array $results, array $options = []): array
    {
        $progIds = array_column($results, 'id');
        if (empty($progIds))
            return $results;

        $query = (new User())->get()->where(['program_id' => ['in' => $progIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
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
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLecturersRelation(array $results, array $options = []): array
    {
        $progIds = array_column($results, 'id');
        if (empty($progIds))
            return $results;

        $query = (new User())->get()->where(['program_id' => ['in' => $progIds], '!role' => ['in' => ['user', 'admin']]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersGrouped = [];
        foreach ($users as $user) {
            $usersGrouped[$user->program_id][] = $user;
        }

        foreach ($results as &$row) {
            $row['lecturers'] = $usersGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLessonsRelation(array $results, array $options = []): array
    {
        $progIds = array_column($results, 'id');
        if (empty($progIds))
            return $results;

        $query = (new Lesson())->get()->where(['program_id' => ['in' => $progIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->program_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['lessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    public function getActiveLabel(): string
    {
        if ($this->active) {
            return "<span class='badge bg-success'>Aktif</span>";
        } else {
            return "<span class='badge bg-danger'>Pasif</span>";
        }
    }
}