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
    /**
     * Model sınıfındaki fill metodunda hangi alanların datetime olduğunu bellirtmek için kullanılır
     * @var array|string[]
     */
    protected array $dateFields = ['register_date', 'last_login'];
    protected array $excludeFromDb = [];
    protected string $table_name = "users";

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

    /**
     * @return Department | null
     * @throws Exception
     */
    public function getDepartment(): Department|null
    {
        return (new Department())->find($this->department_id);

    }

    /**
     * Bölüm Adının döner
     * @return string
     * @throws Exception
     */
    public function getDepartmentName(): string
    {
        return $this->getDepartment()->name ?? "";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getProgramName(): string
    {
        return (new Program())->find($this->program_id)->name ?? "";
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

    /**
     * Kullanıcıya ait derslerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getLessonsList(): array
    {
        return (new Lesson())->get()->where(['lecturer_id' => $this->id])->all() ?? [];
    }

    public function getGravatarURL($size = 50): string
    {
        $default = "";
        return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->mail))) . "?d=" . urlencode($default) . "&s=" . $size;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['lecturer_id' => $this->id])->count();
    }

    /**
     * hocanın girdiği tüm derslerin mevcutlarının toplamını verir
     * @return int
     */
    public function getTotalStudentCount(): int
    {
        return (new Lesson())->get()->where(['lecturer_id' => $this->id])->sum("size");
    }

    /**
     * @throws Exception
     */
    public function getTotalLessonHours()
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['lecturer_id' => $this->id])->sum('hours');
    }
}