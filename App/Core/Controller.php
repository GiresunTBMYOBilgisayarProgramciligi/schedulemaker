<?php

namespace App\Core;

use Exception;
use PDO;

class Controller
{
    public PDO $database;

    public function __construct()
    {
        $this->database = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    }

    /**
     * todo silinecek. Model yapısı bu işleri üstleniyor artık
     * @param $data
     * @return string
     */
    public function createInsertSQL($data): string
    {
        // Dinamik sütunlar ve parametreler oluştur
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        // Sütun isimlerini ` backtick içine al
        $columns = array_map(fn($col) => "`$col`", $columns);

        // Dinamik SQL sorgusu oluştur
        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table_name, // Sorguyu çalıştıran sınıftan alınır.
            implode(", ", $columns),
            implode(", ", $placeholders)
        );
    }

    /**
     * filtre ile belirtilen koşullara uyan veri sayısını döner
     * @param array|null $filters
     * @return int
     * @throws Exception
     */
    public function getCount(?array $filters = []): int
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'modelName')) {
            throw new Exception('Model adı özelliği tanımlı değil.');
        }
        $model = new $this->modelName;
        return $model->get()->where($filters)->count();
    }


    /**
     * Parametre olarak gelen alanlara göre otomatik koşul oluşturur ve koşullara uyan dersleri dizi olarak döner. Her bir eleman Lesson nesnesidir
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function getListByFilters(?array $filters = null): array
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'modelName')) {
            throw new Exception('Model adı özelliği tanımlı değil.');
        }
        $model = new $this->modelName;
        return $model->get()->where($filters)->all();
    }

}