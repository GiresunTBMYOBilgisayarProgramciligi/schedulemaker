<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Core\Gate;
use Exception;

class UserController extends Controller
{
    protected string $table_name = "users";
    protected string $modelName = "App\Models\User";

    /**
     * @param string $mail
     * @return User|bool
     * @throws Exception
     */
    public function getUserByEmail(string $mail): User|bool
    {
        return $this->getListByFilters(["mail" => $mail])[0] ?? false;
    }

    /**
     *  Giriş Yapmış kullanıcıyı döner. Giriş yapılmamışsa false döner
     * @return User|false
     * @throws Exception
     */
    public function getCurrentUser(): User|false
    {
        $user = false;
        $id = $_SESSION[$_ENV["SESSION_KEY"]] ?? $_COOKIE[$_ENV["COOKIE_KEY"]] ?? null;
        if ($id) {
            $user = (new User())->get()->where(['id' => $id])->with(['department', 'program', 'lessons'])->first() ?: false;
        }
        return $user;
    }

    /**
     * Sadece akademisyen olan kullanıcıların sayısını döner
     * @return int
     * @throws Exception
     */
    public function getAcademicCount(): int
    {
        $userModel = new User();
        return $userModel->get()->where(["!role" => ['in' => ["user", "admin"]]])->count();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLoggedIn(): bool
    {
        if ($this->getCurrentUser())
            return true;
        else
            return false;
    }


    /**
     * Formlarda listelenecek Rol, yetki listesi
     * @return string[]
     * @throws Exception
     */
    public function getRoleList(): array
    {
        $list = [
            "user" => "Kullanıcı",
            "lecturer" => "Akademisyen",
        ];
        if (Gate::allowsRole("admin")) {
            $list = array_merge(
                $list,
                ["department_head" => "Bölüm Başkanı", "submanager" => "Müdür Yardımcısı", "manager" => "Müdür", "admin" => "Yönetici"]
            );
        } elseif (Gate::allowsRole("manager")) {
            $list = array_merge(
                $list,
                ["department_head" => "Bölüm Başkanı", "submanager" => "Müdür Yardımcısı", "manager" => "Müdür"]
            );
        } elseif (Gate::allowsRole("submanager")) {
            $list = array_merge(
                $list,
                ["department_head" => "Bölüm Başkanı",]
            );
        }
        return $list;
    }

    /**
     * Formlarda listelenecek Ünvan verileri
     * @return string[]
     */
    public function getTitleList(): array
    {
        return [
            "Araş. Gör.",
            "Öğr. Gör.",
            "Öğr. Gör. Dr.",
            "Dr. Öğr. Üyesi",
            "Doç. Dr.",
            "Prof. Dr."
        ];
    }

    /**
     * Ünvan Ad Soyad şeklinde verilen ismi ünvan, ad, soyad şeklinde ayırarak bir dizi döndürür
     * @param $fullName
     * @return array
     */
    public function parseAcademicName($fullName): array
    {
        // Olası ünvanlar
        $titles = $this->getTitleList();

        // Ünvanları uzunluklarına göre sırala (en uzundan en kısaya)
        // Bu şekilde "Öğr. Gör." yerine "Öğr. Gör. Dr." gibi daha uzun ünvanları önce yakalayacağız
        usort($titles, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        $title = '';
        $nameLastName = '';

        // Ünvanları kontrol et
        foreach ($titles as $possibleTitle) {
            if (strpos($fullName, $possibleTitle) === 0) {
                $title = $possibleTitle;
                // Ünvanı kaldır ve trim yap
                $nameLastName = trim(substr($fullName, strlen($possibleTitle)));
                break;
            }
        }

        // Eğer ünvan bulunamadıysa tüm stringi isim soyisim olarak al
        if (empty($title)) {
            $nameLastName = trim($fullName);
        }

        // Ad ve soyadı ayır - son kelime soyadı olacak
        $nameParts = explode(' ', $nameLastName);
        $lastName = array_pop($nameParts); // Son kelimeyi al (soyad)
        $name = implode(' ', $nameParts); // Kalan kısmı ad olarak birleştir

        return [
            'title' => $title,
            'name' => $name,
            'last_name' => $lastName
        ];
    }

    /**
     * @param string $fullName
     * @return User|bool
     * @throws Exception
     */
    public function getUserByFullName(string $fullName): User|bool
    {
        $filters = $this->parseAcademicName($fullName);
        return $this->getListByFilters($filters)[0] ?? false;
    }
}