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

    public function fill($data = [])// todo bu metod model sınıfına taşınarak her modelde düzgün çelışacak şekilde ayarlanmalı
    {
        // ReflectionClass ile alt sınıfın özelliklerini alın
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Tarih alanı kontrolü
            if (property_exists($this, 'dateFields') && in_array($propertyName, $this->dateFields)) {
                $this->$propertyName = isset($data[$propertyName]) && $data[$propertyName] !== null
                    ? new \DateTime($data[$propertyName])
                    : null;
            } else {
                // Diğer alanlarda null kontrolü
                $this->$propertyName = $data[$propertyName] ?? $this->$propertyName;
            }
        }
    }

    /**
     * Modelin tüm public özelliklerini döner, null olanları ve istenmeyen alanları hariç tutar.
     * @param array $excludedProperties Hariç tutulacak özellikler
     * @return array
     */
    public function getArray($excludedProperties = ['table_name', 'database'], $acceptNull = false)
    {
        // ReflectionClass kullanarak sadece public özellikleri alın
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        // Özellikleri filtrele
        $result = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $this->$name;

            // Özellik excluded listede yoksa ve değeri null değilse ekle
            if (!in_array($name, $excludedProperties)) {
                if ($acceptNull) {
                    $result[$name] = $value;
                } else {
                    if ($value !== null) {
                        $result[$name] = $value;
                    }
                }

            }
        }

        return $result;
    }
}