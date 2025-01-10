<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use PDO;
use PDOException;

class UserController extends Controller
{
    protected string $table_name = "users";

    public function getUser($id)
    {
        if (!is_null($id)) {
            try {
                $u = $this->database->prepare("select * from $this->table_name where id=:id");
                $u->bindValue(":id", $id, PDO::PARAM_INT);
                $u->execute();
                $u = $u->fetch(\PDO::FETCH_ASSOC);
                if ($u) {
                    $user = new User();
                    $user->fill($u);

                    return $user;
                } else throw new \Exception("User not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    /**
     *  Giriş Yapmış kullanıcıyı döner. Giriş yapılmamışsa false döner
     * @return User|false
     */
    public function getCurrentUser()
    {
        $u = false;
        if (isset($_SESSION[$_ENV["SESSION_KEY"]])) {
            $u = $this->getUser($_SESSION[$_ENV["SESSION_KEY"]]) ?? false;
        } elseif (isset($_COOKIE[$_ENV["COOKIE_KEY"]])) {
            $u = $this->getUser($_COOKIE[$_ENV["COOKIE_KEY"]]) ?? false;
        }
        return $u;
    }

    public function getAcademicCount()
    {
        try {
            $count = $this->database->query("SELECT COUNT(*) FROM " . $this->table_name . " WHERE role not in ('user','admin')")->fetchColumn();
            return $count; // İlk sütun (COUNT(*) sonucu) döndür
        } catch (\Exception $e) {
            var_dump($e);
            return false;
        }
    }

    public function isLoggedIn()
    {
        if (isset($_COOKIE[$_ENV["COOKIE_KEY"]]) || isset($_SESSION[$_ENV["SESSION_KEY"]])) return true; else
            return false;
    }

    /**
     * @param array $arr
     * @return void
     * @throws \Exception
     * @see AjaxRouter->loginAction()
     */
    public function login(array $arr): void
    {
        $arr = (object)$arr;
        $stmt = $this->database->prepare("SELECT * FROM users WHERE mail = :mail");
        $stmt->bindParam(':mail', $arr->mail, \PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt) {
            $user = $stmt->fetch(\PDO::FETCH_OBJ);
            if ($user) {
                if (password_verify($arr->password, $user->password)) {
                    if (!$arr->remember_me) {
                        $_SESSION[$_ENV["SESSION_KEY"]] = $user->id;
                    } else {
                        setcookie($_ENV["COOKIE_KEY"], $user->id, [
                            'expires' => time() + (86400 * 30),
                            'path' => '/',
                            'httponly' => true,     // JavaScript erişimini engeller
                            'samesite' => 'Strict', // CSRF saldırılarına karşı koruma
                        ]);
                    }
                } else throw new \Exception("Şifre Yanlış");
            } else throw new \Exception("Kullanıcı kayıtlı değil");
        } else throw new \Exception("Hiçbir kullanıcı kayıtlı değil");
        // Update las login date
        $sql = "UPDATE $this->table_name SET last_login = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$user->id]);
    }

    /**
     * User modeli ile gelen verilele yani kullanıcı oluşturur
     * @param array $data
     * @return array
     * @see AjaxRouter->addUserAction
     */
    public function saveNew(User $new_user): array
    {
        try {
            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_user_arr = $new_user->getArray(['table_name', 'database', 'id', "register_date", "last_login"]);
            $new_user_arr["password"] = password_hash($new_user_arr["password"], PASSWORD_DEFAULT);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_user_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_user_arr);

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    /**
     * User modeli ile gele kullanıcı bilgilerini günceller
     * @param User $user
     * @return string[]
     * @see AjaxRouter->updateUserAction
     */
    public function updateUser(User $user): array
    {
        try {
            // Şifre kontrolü ve hash işlemi
            if (empty($user->password)) {
                $user->password = null;
            } else {
                $user->password = password_hash($user->password, PASSWORD_DEFAULT);
            }

            // Kullanıcı verilerini filtrele
            $userData = $user->getArray(['table_name', 'database', 'id', "register_date", "last_login"], true);

            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($userData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $user->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );

            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz." . $e->getMessage()];
            } else {
                // Diğer PDO hataları
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];

    }

    /**
     * tüm kullanıcıların User Modeli şeklinde oluşturulmuş listesini döner
     * @return array
     */
    public function getUsersList(): array
    {
        $q = $this->database->prepare("SELECT * FROM $this->table_name ");
        $q->execute();
        $user_list = $q->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($user_list as $user_data) {
            $user = new User();
            $user->fill($user_data);
            $users[] = $user;
        }
        return $users;
    }

    /**
     * Rolü kullanıcı dışında olan kullanıcıların listesi
     * @return array
     */
    public function getLecturerList(): array
    {
        $q = $this->database->prepare("SELECT * FROM $this->table_name where not role='user'");
        $q->execute();
        $user_list = $q->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($user_list as $user_data) {
            $user = new User();
            $user->fill($user_data);
            $users[] = $user;
        }
        return $users;
    }

    /**
     * Formlarda listelenecek Rol, yetki listesi
     * @return string[]
     */
    public function getRoleList(): array
    {
        return [
            "user" => "Kullanıcı",
            "lecturer" => "Akademisyen",
            "admin" => "Yönetici",
            "department_head" => "Bölüm Başkanı",
            "manager" => "Müdür",
            "submanager" => "Müdür Yardımcısı"
        ];
    }

    /**
     * Formlarda listelenecek Ünvan verileri
     * @return string[]
     */
    public function getTitleList(): array
    {
        return [
            "Öğr. Gör.",
            "Öğr. Gör. Dr.",
            "Dr. Öğr. Üyesi",
            "Doç. Dr.",
            "Prof. Dr."
        ];
    }

    /**
     * işlemlerin yapılıp yapılamayacağına dair kontrolü yapan fonksiyon.
     * Eğer işlem için gerekli yetki seviyesi kullanıcının yetki seviyesinden küçükse kullanıcı işlemi yapmaya yetkilidir.
     * @param int $actionLevel
     * @return bool
     */
    public static function canUserDoAction(int $actionLevel): bool
    {
        //todo her kullanıcının kendi bilgilerini düzenleyebilmesi için bir düzenleme yapmak lazım
        $user = (new UserController)->getCurrentUser();
        $roleLevels = [
            "admin" => 10,
            "manager" => 9,
            "submanager" => 8,
            "department_head" => 7,
            "lecturer" => 6,
            "user" => 5
        ];

        return $roleLevels[$user->role] >= $actionLevel;
    }
}