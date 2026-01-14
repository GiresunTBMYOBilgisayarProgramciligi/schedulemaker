<?php

namespace App\Models;

use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class Department extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $chairperson_id = null;

    public ?bool $active = null;

    public ?User $chairperson = null;
    public array $programs = [];
    public array $users = [];
    public array $lessons = [];
    protected array $excludeFromDb = ['chairperson', 'programs', 'users', 'lessons'];
    protected string $table_name = "departments";

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getChairpersonRelation(array $results, array $options = []): array
    {
        $userIds = array_unique(array_column($results, 'chairperson_id'));
        if (empty($userIds))
            return $results;

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $user) {
            $usersKeyed[$user->id] = $user;
        }

        foreach ($results as &$row) {
            if (isset($row['chairperson_id']) && isset($usersKeyed[$row['chairperson_id']])) {
                $row['chairperson'] = $usersKeyed[$row['chairperson_id']];
            } else {
                $row['chairperson'] = null;
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
    public function getProgramsRelation(array $results, array $options = []): array
    {
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $query = (new Program())->get()->where(['department_id' => ['in' => $deptIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $programs = $query->all();
        $programsGrouped = [];
        foreach ($programs as $prog) {
            $programsGrouped[$prog->department_id][] = $prog;
        }

        foreach ($results as &$row) {
            $row['programs'] = $programsGrouped[$row['id']] ?? [];
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
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $query = (new User())->get()->where(['department_id' => ['in' => $deptIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersGrouped = [];
        foreach ($users as $user) {
            $usersGrouped[$user->department_id][] = $user;
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
    public function getLessonsRelation(array $results, array $options = []): array
    {
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $query = (new Lesson())->get()->where(['department_id' => ['in' => $deptIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->department_id][] = $lesson;
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