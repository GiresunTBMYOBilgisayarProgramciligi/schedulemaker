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

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getChairpersonRelation(array $results): array
    {
        $userIds = array_unique(array_column($results, 'chairperson_id'));
        if (empty($userIds))
            return $results;

        $users = (new User())->get()->where(['id' => ['in' => $userIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getProgramsRelation(array $results): array
    {
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $programs = (new Program())->get()->where(['department_id' => ['in' => $deptIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getUsersRelation(array $results): array
    {
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $users = (new User())->get()->where(['department_id' => ['in' => $deptIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getLessonsRelation(array $results): array
    {
        $deptIds = array_column($results, 'id');
        if (empty($deptIds))
            return $results;

        $lessons = (new Lesson())->get()->where(['department_id' => ['in' => $deptIds]])->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->department_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['lessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    protected string $table_name = "departments";


    /**
     * Bölüm başkanı Modelini döner. Eğer bölüm başkanı tanımlı değilse Boş Model döner
     * @return User | null Chair Person
     * @throws Exception
     */
    public function getChairperson(): ?User
    {
        if (is_null($this->chairperson_id)) {
            return new User(); // bölüm başkanı tanımlı değilse boş kullanıcı döndür.
        } else
            return (new User())->find($this->chairperson_id);
    }

    public function getProgramCount(): int
    {
        return (new Program())->get()->where(['department_id' => $this->id])->count();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPrograms(): array
    {
        return (new Program())->get()->where(['department_id' => $this->id])->all();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
        return (new User())->get()->where(['department_id' => $this->id, '!role' => 'user'])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        return (new User())->get()->where(['department_id' => $this->id, '!role' => 'user'])->count();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
        return (new Lesson())->get()->where(['department_id' => $this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        return (new Lesson())->get()->where(['department_id' => $this->id])->count();
    }
}