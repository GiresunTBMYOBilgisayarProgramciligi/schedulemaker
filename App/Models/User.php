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
    public int $schedule_id;
    public \DateTime $register_date;
    public \DateTime $last_login;

    private string $table_name = "users";

    /**
     *
     */
    public function __construct()
    {
        parent::__construct(); # Connect to database
    }

    /**
     * @param $data array anahtarları users tablosunun alanları olan bir dizi
     * @return void
     */
    public function fillUser($data = [])// todo bu metod model sınıfına taşınarak her modelde düzgün çelışacak şekilde ayarlanmalı
    {
        foreach (get_class_vars(get_class($this)) as $k => $v) {
            // Eğer tarih alanları ise string değeri DateTime nesnesine dönüştür
            if ($k == 'register_date' || $k == 'last_login') {
                // Tarih değeri var mı kontrol et
                if (isset($data[$k]) && $data[$k] !== null) {
                    $this->$k = new \DateTime($data[$k]);
                } else {
                    // Eğer tarih yoksa, geçerli bir DateTime nesnesi oluştur
                    $this->$k = new \DateTime();
                }
            } else {
                // Diğer alanlarda null kontrolü
                if (isset($data[$k]) && $data[$k] !== null) {
                    $this->$k = $data[$k];
                }
            }
        }
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