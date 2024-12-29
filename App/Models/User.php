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
    public string $schedule;
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
    public function fillUser($data = [])
    {
        foreach (get_class_vars(get_class($this)) as $k => $v) {
            // Eğer tarih alanları ise string değeri DateTime nesnesine dönüştür
            if ($k == 'register_date' || $k == 'last_login') {
                $this->$k = new \DateTime($data[$k]);
            } else {
                if (!is_null($data[$k])) {
                    $this->$k = $data[$k];
                }
            }
        }
    }

    public function getFullName()
    {
        return $this->name . " " . $this->last_name;
    }

    public function getGravatarURL($size = 50)
    {
        $default = "/admin/dist/img/avatar.png";
        return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->mail))) . "?d=" . urlencode($default) . "&s=" . $size;
    }

    /**
     * @param $excludedProperties  array diziye eklenmesi istenmeyen özellikler
     * @return array
     */
    public function getArray($excludedProperties = ['table_name', 'database'])
    {
        $properties = get_object_vars($this);//sadece değeri olan alanları alıyor
        return array_filter($properties, function ($key) use ($excludedProperties) {
            return !in_array($key, $excludedProperties);
        }, ARRAY_FILTER_USE_KEY);
    }
}