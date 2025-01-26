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

    public function createInsertSQL($data): string
    {
        // Dinamik sütunlar ve parametreler oluştur
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        // Dinamik SQL sorgusu oluştur
        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table_name,//sorguyu çalıştıran sınıftan alınır.
            implode(", ", $columns),
            implode(", ", $placeholders)
        );
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

    /**
     * Parametre olarak gelen alanlara göre otomatik koşul oluşturur ve koşullara uyan dersleri dizi olarak döner. Her bir eleman Lesson nesnesidir
     * @param array|null $filters
     * @return array
     * @throws \Exception
     */
    public function getListByFilters(array $filters = null): array
    {
        try {
            if (!is_null($filters)) {
                // Koşullar ve parametreler
                $conditions = [];
                $parameters = [];

                // Parametrelerden WHERE koşullarını oluştur
                foreach ($filters as $column => $value) {
                    $isNotCondition = false;

                    // Eğer anahtar '!' ile başlıyorsa, NOT koşulu
                    if (str_starts_with($column, '!')) {
                        $isNotCondition = true;
                        $column = ltrim($column, '!'); // '!' işaretini kaldır
                    }

                    if (is_array($value) and count($value) > 0) {
                        // Eğer değer bir array ise, IN ifadesi oluştur
                        $placeholders = [];
                        foreach ($value as $index => $item) {
                            $placeholder = ":{$column}_{$index}";
                            $placeholders[] = $placeholder;
                            $parameters[$placeholder] = $item;
                        }
                        $conditions[] = $isNotCondition
                            ? "$column NOT IN (" . implode(", ", $placeholders) . ")"
                            : "$column IN (" . implode(", ", $placeholders) . ")";
                    } else {
                        // Normal eşitlik kontrolü
                        $conditions[] = $isNotCondition
                            ? "$column != :$column"
                            : "$column = :$column";
                        $parameters[":$column"] = $value;
                    }
                }

                // WHERE ifadesini oluştur
                $whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
            } else $whereClause = "";


            // Sorguyu hazırla
            $sql = "SELECT * FROM $this->table_name $whereClause";
            $stmt = $this->database->prepare($sql);

            // Parametreleri bağla
            foreach ($parameters as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            // Verileri işle
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $models = [];

            if ($result) {
                // Alt sınıfta table_name tanımlı mı kontrol et
                if (!property_exists($this, 'modelName')) {
                    throw new \Exception('Model Adı özelliği tanımlı değil.');
                }
                foreach ($result as $data) {
                    $model = new $this->modelName();
                    $model->fill($data);
                    $models[] = $model;
                }
            }
            return $models;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
            //todo hata yönetimi üzerine çalışırken eğer başka bir try içerisinde çalıştırılan bir metod ise yakalanan hataların yeniden
            // throw ile fırlatılması gerekiyor. Bunun tüm kodlarda analiz edip düzenlemek lazım
        }
    }
}