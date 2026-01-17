<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use App\Core\Gate;
use Exception;
use PDO;
use PDOException;

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
     * @param array $loginData
     * @return void
     * @throws Exception
     * @see AjaxRouter->loginAction()
     */
    public function login(array $loginData): void
    {
        $loginData = (object) $loginData;
        $stmt = $this->database->prepare("SELECT * FROM users WHERE mail = :mail");
        $stmt->bindParam(':mail', $loginData->mail);
        $stmt->execute();

        if ($stmt) {
            $user = $stmt->fetch(\PDO::FETCH_OBJ);
            if ($user) {
                if (password_verify($loginData->password, $user->password)) {
                    if (!$loginData->remember_me) {
                        $_SESSION[$_ENV["SESSION_KEY"]] = $user->id;
                    } else {
                        setcookie($_ENV["COOKIE_KEY"], $user->id, [
                            'expires' => time() + (86400 * 30),
                            'path' => '/',
                            'httponly' => true,     // JavaScript erişimini engeller
                            'samesite' => 'Strict', // CSRF saldırılarına karşı koruma
                        ]);
                    }
                    // Log the login
                    $userObj = new User();
                    $userObj->fill((array) $user);
                    $this->logger()->info($userObj->getFullName() . " giriş yaptı.", $this->logContext([
                        'user_id' => $user->id,
                        'username' => $userObj->getFullName()
                    ]));
                } else
                    throw new Exception("Şifre Yanlış");
            } else
                throw new Exception("Kullanıcı kayıtlı değil");
        } else
            throw new Exception("Hiçbir kullanıcı kayıtlı değil");
        // Update las login date
        $sql = "UPDATE $this->table_name SET last_login = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$user->id]);
    }

    /**
     * User modeli ile gelen verilele yani kullanıcı oluşturur
     * @param array $userData
     * @return int
     * @throws Exception
     * @see AjaxRouter->addUserAction
     */
    public function saveNew(array $userData): int
    {
        try {
            // Yeni kullanıcı verilerini bir dizi olarak alın
            $userData["password"] = password_hash($userData["password"] ?? "123456", PASSWORD_DEFAULT);

            $new_user = new User();
            $new_user->fill($userData);
            $new_user->create();
            return $new_user->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
    }

    /**
     * User modeli ile gele kullanıcı bilgilerini günceller
     * @param User $user
     * @return int
     * @throws Exception,
     * @see AjaxRouter->updateUserAction
     */
    public function updateUser(User $user): int
    {
        try {
            // Şifre kontrolü ve hash işlemi
            if (!empty($user->password)) {
                $user->password = password_hash($user->password, PASSWORD_DEFAULT);
            } else {
                $user->password = null;
            }

            // Kullanıcı verilerini filtrele
            /*
             * Array filter boş değerleri siliyor böylece şifre null değilse ise null değeri diziden silinmiş oluyor.
             * Bu ekleme şifre boş bırakıldığında şifrenin silinmesi hatasını çözmek için eklendi
             */
            $userData = $user->getArray(array_filter([
                'id',
                'register_date',
                'last_login',
                !is_null($user->password) ? null : 'password'
            ]), true);

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
            return $user->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
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


    /**
     * @param int $id Silinecek dersin id numarası
     * @throws Exception
     */
    public function delete(int $id): void
    {
        $user = (new User())->find($id) ?: throw new Exception("Silinecek Kullanıcı bulunamadı");
        (new ScheduleController())->wipeResourceSchedules('user', $id);
        $user->delete();
    }
}