<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * users tablosundaki her biri kayıtı temsil eden sınıf
 */
class User extends Model
{
    public int $id;
    public string $password;
    public string $mail;
    public string $name;
    public string $last_name;
    public string $role;
    public string $title;
    public int $department_id;
    public int $program_id;
    public ?int $schedule_id=null;
    public ?\DateTime $register_date;
    public ?\DateTime $last_login;
    /**
     * Model sınıfındaki fill metodunda hangi alanların datetime olduğunu bellirtmek için kullanılır
     * @var array|string[]
     */
    protected array $dateFields = ['register_date', 'last_login'];

    private string $table_name = "users";

    /**
     *
     */
    public function __construct()
    {
        parent::__construct(); # Connect to database
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
    public function getFullName()
    {
        return $this->title . " " . $this->name . " " . $this->last_name;
    }

    /**
     * Bölüm Adının döner
     * @return void
     */
    public function getDepartmentName()
    {
        return "Bilgisayar Teknolojileri";
    }

    public function getProgramName()
    {
        return "Bilgisayar Programcılığı";
    }

    public function getRoleName()
    {
        $role_names = [
            "user" => "Kullanıcı",
            "lecturer" => "Akademisyen",
            "admin" => "Yönetici",
            "department_head" => "Bölüm Başkanı",
            "manager" => "Müdür",
            "submanager" => "Müdür Yardımcısı"
        ];
        return $role_names[$this->role];
    }

    public function getGravatarURL($size = 50)
    {
        $default = "";
        return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->mail))) . "?d=" . urlencode($default) . "&s=" . $size;
    }
}