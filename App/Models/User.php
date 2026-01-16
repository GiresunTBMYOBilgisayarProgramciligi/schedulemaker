<?php

namespace App\Models;

use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Core\Model;
use Exception;

/**
 * users tablosundaki her biri kayıtı temsil eden sınıf
 */
class User extends Model
{
    public ?int $id = null;
    public ?string $password = null;
    public ?string $mail = null;
    public ?string $name = null;
    public ?string $last_name = null;
    public ?string $role = null;
    public ?string $title = null;
    public ?int $department_id = null;
    public ?int $program_id = null;
    public ?\DateTime $register_date = null;
    public ?\DateTime $last_login = null;

    public ?Department $department = null;
    public ?Program $program = null;
    public array $schedules = [];
    public array $lessons = [];
    protected array $dateFields = ['register_date', 'last_login'];
    protected array $excludeFromDb = ['department', 'program', 'schedules', 'lessons'];
    protected string $table_name = "users";

    public function getLabel(): string
    {
        return "kullanıcı";
    }

    public function getLogDetail(): string
    {
        return $this->getFullName();
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLessonsRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Lesson())->get()
            ->where([
                'lecturer_id' => ['in' => $ids]
            ]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();

        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->lecturer_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['lessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getSchedulesRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Schedule())->get()
            ->where([
                'owner_type' => 'user',
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
        $departmentIds = array_unique(array_column($results, 'department_id'));
        if (empty($departmentIds))
            return $results;

        $query = (new Department())->get()->where(['id' => ['in' => $departmentIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $departments = $query->all();
        $departmentsKeyed = [];
        foreach ($departments as $dept) {
            $departmentsKeyed[$dept->id] = $dept;
        }

        foreach ($results as &$userRow) {
            if (isset($userRow['department_id']) && isset($departmentsKeyed[$userRow['department_id']])) {
                $userRow['department'] = $departmentsKeyed[$userRow['department_id']];
            } else {
                $userRow['department'] = null;
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
    public function getProgramRelation(array $results, array $options = []): array
    {
        $programIds = array_unique(array_column($results, 'program_id'));
        if (empty($programIds))
            return $results;

        $query = (new Program())->get()->where(['id' => ['in' => $programIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $programs = $query->all();
        $programsKeyed = [];
        foreach ($programs as $prog) {
            $programsKeyed[$prog->id] = $prog;
        }

        foreach ($results as &$userRow) {
            if (isset($userRow['program_id']) && isset($programsKeyed[$userRow['program_id']])) {
                $userRow['program'] = $programsKeyed[$userRow['program_id']];
            } else {
                $userRow['program'] = null;
            }
        }
        return $results;
    }

    public function getRegisterDate(): string
    {
        return !is_null($this->register_date) ? $this->register_date->format('Y-m-d H:i:s') : "";
    }

    public function getLastLogin(): string
    {
        return !is_null($this->last_login) ? $this->last_login->format('Y-m-d H:i:s') : "Hiç Giriş Yapılmadı";
    }

    /**
     * Kullanıdı Adı ve Soyadını birleştirerek döner
     * @return string
     */
    public function getFullName(): string
    {
        return trim($this->title . " " . $this->name . " " . $this->last_name);
    }

    public function getRoleName(): string
    {
        $role_names = [
            "user" => "Kullanıcı",
            "lecturer" => "Akademisyen",
            "admin" => "Yönetici",
            "department_head" => "Bölüm Başkanı",
            "manager" => "Müdür",
            "submanager" => "Müdür Yardımcısı"
        ];
        return $role_names[$this->role] ?? "";
    }

    public function getGravatarURL($size = 50): string
    {
        $default = "";
        return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->mail))) . "?d=" . urlencode($default) . "&s=" . $size;
    }
}