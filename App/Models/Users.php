<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Users extends Model
{
    public int $id;
    public string $user_name;
    public string $password;
    public string $mail;
    public string $name;
    public string $last_name;
    public \DateTime $register_date;
    public \DateTime $last_login;

    private string $table_name = "users";

    /**
     * @param int $id
     */
    public function __construct(int $id = null)
    {
        parent::__construct(); # Connect to database
        if (isset($id)) {
            $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
            $q->execute(["id" => $id]);
            $data = $q->fetchAll();
            extract($data);
            $this->id = $id;
            $this->name = $name;
            $this->user_name = $user_name;
            $this->password = $password;
            $this->mail = $mail;
            $this->name =$name;
            $this->last_name = $last_name;
            $this->register_date = date('d-m-Y H:i:s', timestamp: $register_date);
            $this->last_login =date('d-m-Y H:i:s', timestamp: $last_login);
        }
    }

    public function save_new(){
        /*
         * todo insert class data to database
         *  admin controller create_new_user metodu ile oluşturulan yeni user modeli ile alınan veriler veri tabanına kaydedilecek
         */
    }
    public function get_user_list(){
        $q= $this->database->prepare("SELECT * FROM $this->table_name ");
        $q->execute();
        return $q->fetchAll(PDO::FETCH_OBJ);
    }
}