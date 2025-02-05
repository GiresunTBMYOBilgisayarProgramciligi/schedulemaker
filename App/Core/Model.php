<?php

namespace App\Core;


use Exception;
use PDO;
use App\Controllers\ProgramController;

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

    public function is_data_serialized($data)
    {
        // Boş bir string serileştirilmiş kabul edilmez
        if (!is_string($data) || trim($data) === '') {
            return false;
        }

        // Serileştirilmiş format kontrolü
        $data = trim($data);
        if (preg_match('/^([adObis]):/', $data, $matches)) {
            switch ($matches[1]) {
                case 'a': // array
                case 'O': // object
                case 's': // string
                case 'b': // boolean
                case 'i': // integer
                case 'd': // double
                    return @unserialize($data) !== false || $data === 'b:0;'; // "b:0;" özel durum
            }
        }

        return false;
    }

    public function fill($data = [])
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
                $data[$propertyName] = $this->is_data_serialized($data[$propertyName]) ? unserialize($data[$propertyName]) : $data[$propertyName];
                $this->$propertyName = $data[$propertyName] ?? $this->$propertyName;
            }
        }
    }

    /**
     * Ekleme ve düzenleme sayfalarında oluşturulacak program listesini oluşturur.
     * Bölümü tanımlanmamış bir ders ise sadece program seçiniz verisi olur.
     * Eğer bölümü olan bir ders ise sadece o programa ait liste gözükür
     * @return object[]
     * @throws Exception
     */
    public function getDepartmentProgramsList(): array
    {
        try {
            if (property_exists($this, 'department_id')) {
                if (is_null($this->department_id)) {
                    $list = [(object)["id" => 0, "name" => "Program Seçiniz"]];
                } else {
                    $list = (new ProgramController())->getProgramsList($this->department_id);
                    array_unshift($list, (object)["id" => 0, "name" => "Program Seçiniz"]);
                }
            } else $list = [];
            return $list;
        }catch (Exception $exception){
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
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