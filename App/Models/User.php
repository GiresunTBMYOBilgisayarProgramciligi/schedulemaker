<?php

namespace App\Models;

use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Core\Logger;
use App\Core\Model;
use Exception;
use PDO;

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
     * @return Department | false
     * @throws Exception
     */
    public function getDepartment(): Department|false
    {
        return (new DepartmentController())->getDepartment($this->department_id);
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
        return (new ProgramController())->getProgram($this->program_id)->name ?? "";

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
        return (new LessonController())->getLessonsList($this->id) ?? [];
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
        $stmt = $this->database->prepare("SELECT COUNT(*) as count FROM lessons WHERE lecturer_id = :id");
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data['count'];

    }

    /**
     * todo
     * @return int
     */
    public function getTotalStudentCount(): int
    {
        return 100;
    }

    /**
     * @throws Exception
     */
    public function getTotalLessonHours()
    {
        $stmt = $this->database->prepare("SELECT Sum(hours) as total FROM lessons WHERE lecturer_id = :id");
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data['total'];
    }
}