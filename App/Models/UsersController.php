<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class UsersController extends Model
{
    private $table_name = "users";

    public function getUser($id)
    {
        if (!is_null($id)) {
            try {
                $u = $this->database->prepare("select * from users where id=:id");
                $u->bindValue(":id", $id, PDO::PARAM_INT);
                $u->execute();
                $u = $u->fetch(\PDO::FETCH_ASSOC);
                if ($u) {
                    $user = new User();
                    $user->fillUser($u);

                    return $user;
                }else throw new \Exception("User not found");
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

    public function getCount(){
        try {
            $count = $this->database->query("SELECT COUNT(*) FROM " . $this->table_name)->fetchColumn();
            return $count; // İlk sütun (COUNT(*) sonucu) döndür
        }catch (\Exception $e){
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
                    if (!$arr->remember_me){
                        $_SESSION[$_ENV["SESSION_KEY"]] = $user->id;
                    }else{
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

    }
    /**
     * AjaxControllerdan gelen verilele yani kullanıcı oluşturur
     * @param array $data
     * @return array
     */
    public function save_new(array $data): array
    {
        $new_user = new User();
        $new_user->fillUser($data);
        try {
            $q = $this->database->prepare(
                "INSERT INTO users(password, mail, name, last_name, role,title, department_id, program_id) 
            values  (:password, :mail, :name, :last_name, :role,:title, :department_id, :program_id)");
            if ($q) {
                $new_user_arr = $new_user->getArray(['table_name', 'database', 'id',"register_date","last_login"]);
                $new_user_arr["password"] = password_hash($new_user_arr["password"], PASSWORD_DEFAULT);
                $q->execute($new_user_arr);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.".$e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function get_users_list()
    {
        $q = $this->database->prepare("SELECT * FROM $this->table_name ");
        $q->execute();
        $user_list= $q->fetchAll(PDO::FETCH_ASSOC);
        $users=[];
        foreach ($user_list as $user_data) {
            $user = new User();
            $user->fillUser($user_data);
            $users[] = $user;
        }
        return $users;
    }
}