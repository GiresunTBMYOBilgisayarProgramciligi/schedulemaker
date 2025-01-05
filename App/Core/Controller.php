<?php

namespace App\Core;

use PDO;

class Controller
{
    public PDO $database;

    public function __construct()
    {
        try {
            $this->database = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
        } catch (\PDOException $exception) {
            echo $exception->getMessage();
        }

    }

    public function getCount()
    {
        try {
            // Alt sınıfta table_name tanımlı mı kontrol et
            if (!property_exists($this, 'table_name')) {
                throw new \Exception('Table name özelliği tanımlı değil.');
            }
            $count = $this->database->query("SELECT COUNT(*) FROM " . $this->table_name)->fetchColumn();
            return $count; // İlk sütun (COUNT(*) sonucu) döndür
        } catch (\Exception $e) {
            var_dump($e);
            return false;
        }
    }

    public function delete($id = null)
    {
        try {
            if (is_null($id)) {
                return ['error' => 'Geçerli bir ID sağlanmadı.'];
            }
            // Alt sınıfta table_name tanımlı mı kontrol et
            if (!property_exists($this, 'table_name')) {
                throw new \Exception('Table name özelliği tanımlı değil.');
            }

            $stmt = $this->database->prepare("DELETE FROM {$this->table_name} WHERE id = :id");
            $stmt->execute([":id" => $id]);

            if (!$stmt->rowCount() > 0) {
                throw new \Exception('Kayıt bulunamadı veya silinemedi.');
            }
        } catch (\Exception $e) {
            return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
        }
    }
}