<?php

namespace App\Core;


use PDO;

class Model
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

    /**
     * Modelin tüm özelliklerini döner ve istenmeyen alanları hariç tutar.
     * @param array $excludedProperties Hariç tutulacak özellikler
     * @return array
     */
    public function getArray($excludedProperties = ['table_name', 'database'])
    {
        // Modelin özelliklerini al
        $properties = get_object_vars($this);
        // Özellikleri filtrele
        return array_filter($properties, function ($key) use ($excludedProperties) {
            return !in_array($key, $excludedProperties);
        }, ARRAY_FILTER_USE_KEY);
    }
}