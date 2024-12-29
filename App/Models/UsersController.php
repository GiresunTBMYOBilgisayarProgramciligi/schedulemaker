<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class UsersController extends Model
{
    private $table_name = "users";


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
                "INSERT INTO users(password, mail, name, last_name, role,title, department_id) 
            values  (:password, :mail, :name, :last_name, :role,:title, :department_id)");
            if ($q) {
                $new_user_arr = $new_user->getArray(['table_name', 'database', 'id']);
                $new_user_arr["password"] = password_hash($new_user_arr["password"], PASSWORD_DEFAULT);
                $q->execute($new_user_arr);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz."];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function get_user_list()
    {
        $q = $this->database->prepare("SELECT * FROM $this->table_name ");
        $q->execute();
        return $q->fetchAll(PDO::FETCH_OBJ);
    }
}