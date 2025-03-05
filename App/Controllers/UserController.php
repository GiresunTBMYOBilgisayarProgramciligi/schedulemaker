<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\isAuthorized;

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
        if (isset($_SESSION[$_ENV["SESSION_KEY"]])) {
            $user = (new User())->find($_SESSION[$_ENV["SESSION_KEY"]]) ?? false;
        } elseif (isset($_COOKIE[$_ENV["COOKIE_KEY"]])) {
            $user = (new User())->find($_COOKIE[$_ENV["COOKIE_KEY"]]) ?? false;
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
     */
    public function isLoggedIn(): bool
    {
        if (isset($_COOKIE[$_ENV["COOKIE_KEY"]]) || isset($_SESSION[$_ENV["SESSION_KEY"]])) return true; else
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
        $loginData = (object)$loginData;
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
                } else throw new Exception("Şifre Yanlış");
            } else throw new Exception("Kullanıcı kayıtlı değil");
        } else throw new Exception("Hiçbir kullanıcı kayıtlı değil");
        // Update las login date
        $sql = "UPDATE $this->table_name SET last_login = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$user->id]);
    }

    /**
     * User modeli ile gelen verilele yani kullanıcı oluşturur
     * @param User $new_user
     * @return int
     * @throws Exception
     * @see AjaxRouter->addUserAction
     */
    public function saveNew(User $new_user): int
    {
        try {
            if (!isAuthorized("submanager", false, $new_user)) {
                throw new Exception("Kullanıcı oluşturma yetkiniz yok");
            }
            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_user_arr = $new_user->getArray(['table_name', 'database', 'id', "register_date", "last_login"]);
            $new_user_arr["password"] = password_hash($new_user_arr["password"] ?? "123456", PASSWORD_DEFAULT);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_user_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_user_arr);
            return $this->database->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int)$e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
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
            if (!isAuthorized("submanager", false, $user)) {
                throw new Exception("Kullanıcı bilgilerini güncelleme yetkiniz yok");
            }
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
                'table_name',
                'database',
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
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int)$e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * tüm kullanıcıların User Modeli şeklinde oluşturulmuş listesini döner
     * @return array
     * @throws Exception
     */
    public function getUsersList(): array
    {
        return $this->getListByFilters();
    }

    /**
     * Rolü kullanıcı dışında olan kullanıcıların listesi
     * @param array $filter
     * @return array
     * @throws Exception
     */
    public function getLecturerList(array $filter = []): array
    {
        $filter = array_merge($filter, ["!role" => ['in' => ["user", "admin"]]]);
        return $this->getListByFilters($filter);
    }

    /**
     * Formlarda listelenecek Rol, yetki listesi
     * @return string[]
     */
    public function getRoleList(): array
    {
        $list = [
            "user" => "Kullanıcı",
            "lecturer" => "Akademisyen",
        ];
        if (isAuthorized("admin")) {
            $list = array_merge(
                $list,
                ["department_head" => "Bölüm Başkanı", "submanager" => "Müdür Yardımcısı", "manager" => "Müdür", "admin" => "Yönetici"]
            );
        } elseif (isAuthorized("manager")) {
            $list = array_merge(
                $list,
                ["department_head" => "Bölüm Başkanı", "submanager" => "Müdür Yardımcısı", "manager" => "Müdür"]
            );
        } elseif (isAuthorized("submanager")) {
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
     * işlemlerin yapılıp yapılamayacağına dair kontrolü yapan fonksiyon.
     * Eğer işlem için gerekli yetki seviyesi kullanıcının yetki seviyesinden küçükse kullanıcı işlemi yapmaya yetkilidir.
     * @param int $actionLevel "admin" => 10,
     * "manager" => 9,
     * "submanager" => 8,
     * "department_head" => 7,
     * "lecturer" => 6,
     * "user" => 5
     * @param bool $reverse eğer true girilmişse belirtilen rolden düşük roller için yetki verir
     * @param null $model Kullanıcı ile ilişkisi kontrol edilecek olan model
     * @return bool
     * @throws Exception
     */
    public static function canUserDoAction(int $actionLevel, bool $reverse = false, $model = null): bool
    {
        $user = (new UserController)->getCurrentUser();
        $isOwner = false;
        if (!is_null($model)) {
            switch (get_class($model)) {
                case "App\Models\User":
                    /*
                     * Aktif kullanıcı model kullanıcısı ise yada
                     * Aktif kullanıcı model kullanıcısının bölüm başkanı ise
                     */
                    $department = (new Department())->find($model->department_id ?? 0);
                    $isOwner = ($user->id == $model->id or $user->id == $department?->chairperson_id);
                    break;

                case "App\Models\Lesson":
                    /*
                     * Aktif kullanıcı Dersin sahibi ise yada
                     * Aktif kullanıcı Dersin bölüm başkanı ise
                     */
                    $department = (new Department())->find($model->department_id ?? 0);
                    $isOwner = ($model->lecturer_id == $user->id or $user->id == $department?->chairperson_id);
                    break;

                case "App\Models\Program":
                    /*
                     * Aktif kullanıcı Programın bölüm başkanı ise
                     */
                    $department = (new Department())->find($model->department_id ?? 0);
                    $isOwner = ($user->id == $department?->chairperson_id or $user->program_id == $model->id);
                    break;

                case "App\Models\Department":
                    /*
                     * Aktif kullanıcı Bölüm başkanı ise
                     */
                    $isOwner = ($user->id == $model->chairperson_id or $user->department_id == $model->id);
                    break;

                case "App\Models\Schedule":
                    /*
                     * owner_type program, user, lesson, classroom
                     */
                    switch ($model->owner_type) {
                        case "program":
                            $program = (new Program())->find($model->owner_id ?? 0);
                            $isOwner = $program?->getDepartment()?->chairperson_id == $user->id;
                            break;

                        case "user":
                            $ScheduleUser = (new User())->find($model->owner_id ?? 0);
                            $isOwner = ($ScheduleUser?->getDepartment()?->chairperson_id == $user->id or $ScheduleUser?->id == $user->id);
                            break;

                        case "lesson":
                            $lesson = (new Lesson())->find($model->owner_id ?? 0);
                            $isOwner = $lesson?->getDepartment()?->chairperson_id == $user->id;
                            break;

                        case "classroom":
                            $isOwner = true; // Sınıf için denetim yok
                            break;
                    }
                    break;
            }
        }
        $roleLevels = [
            "admin" => 10,
            "manager" => 9,
            "submanager" => 8,
            "department_head" => 7,
            "lecturer" => 6,
            "user" => 5
        ];
        $isAuthorizedRole = match ($reverse) {
            true => $roleLevels[$user->role] <= $actionLevel,
            false => $roleLevels[$user->role] >= $actionLevel
        };
        return $isAuthorizedRole or $isOwner;
    }

    /**
     * @param int $id Silinecek dersin id numarası
     * @throws Exception
     */
    public function delete(int $id): void
    {
        // ilişkili tüm programı sil
        $schedules = (new Schedule())->get()->where(["owner_type" => "user", "owner_id" => $id])->all();
        foreach ($schedules as $schedule) {
            $schedule->delete();
        }
        (new User())->find($id)->delete();
    }
}