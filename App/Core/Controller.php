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
    public function getListByFilters(array $filters = null): array
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'modelName')) {
            throw new Exception('Model adı özelliği tanımlı değil.');
        }
        $model = new $this->modelName;
        return $model->get()->where($filters)->all();
    }

    /**
     * SQL sorgularının WHERE kısmını dinamik olarak oluşturur.
     *
     * Desteklenen operatörler:
     * - Eşitlik: Direkt değer atama ile (`'column' => 'value'`)
     * - Eşit Değil: Başına ! koyarak (`'!column' => 'value'`)
     * - Büyüktür: `'column' => ['>' => value]`
     * - Büyük Eşittir: `'column' => ['>=' => value]`
     * - Küçüktür: `'column' => ['<' => value]`
     * - Küçük Eşittir: `'column' => ['<=' => value]`
     * - LIKE: `'column' => ['like' => '%value%']`
     * - NOT LIKE: `'column' => ['!like' => '%value%']`
     * - IN: `'column' => ['in' => [1, 2, 3]]`
     * - NOT IN: `'!column' => ['in' => [1, 2, 3]]`
     *
     * Örnek kullanım:
     * $filters = [
     *     'age' => ['>' => 18],                 // age > 18
     *     'price' => ['<=' => 1000],           // price <= 1000
     *     'status' => 'active',                // status = 'active'
     *     '!category' => 'deleted',            // category != 'deleted'
     *     'name' => ['like' => '%john%'],      // name LIKE '%john%'
     *     'tags' => ['in' => [1, 2, 3]],      // tags IN (1,2,3)
     *     'priority' => ['>=' => 5]           // priority >= 5
     * ];
     *
     * $whereClause = '';
     * $parameters = [];
     * $this->prepareWhereClause($filters, $whereClause, $parameters);
     *
     * // Çıktı örneği:
     * // WHERE `age` > :age_0 AND `price` <= :price_0 AND `status` = :status
     * // AND `category` != :category AND `name` LIKE :name_0
     * // AND `tags` IN (:tags_0, :tags_1, :tags_2) AND `priority` >= :priority_0
     *
     * @param array|null $filters Filtre koşullarını içeren dizi. null ise boş WHERE clause döner
     * @param string $whereClause WHERE clause'un atanacağı referans değişken
     * @param array $parameters Prepared statement parametrelerinin atanacağı referans değişken
     * @return void                     whereClause ve parameters değişkenlerini günceller
     *
     * @throws \InvalidArgumentException Geçersiz operatör kullanıldığında
     *
     * @example
     * // Basit eşitlik kontrolü
     * $filters = ['status' => 'active'];
     *
     * // Sayısal karşılaştırma
     * $filters = ['age' => ['>' => 18]];
     *
     * // LIKE sorgusu
     * $filters = ['name' => ['like' => '%john%']];
     *
     * // IN operatörü
     * $filters = ['category_id' => ['in' => [1, 2, 3]]];
     *
     * // Karışık sorgular
     * $filters = [
     *     'age' => ['>' => 18],
     *     '!status' => 'inactive',
     *     'category' => ['in' => [1, 2, 3]]
     * ];
     */
    public function prepareWhereClause(?array $filters, string &$whereClause, array &$parameters): void
    {
        if (is_null($filters)) {
            $whereClause = "";
            $parameters = [];
            return;
        }

        $conditions = [];
        $parameters = [];

        // Desteklenen operatörler
        $operators = [
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            '!=' => '!=',
            '=' => '=',
            'like' => 'LIKE',
            '!like' => 'NOT LIKE'
        ];

        foreach ($filters as $column => $value) {
            $isNotCondition = false;

            // NOT operatörü kontrolü
            if (str_starts_with($column, '!')) {
                $isNotCondition = true;
                $column = ltrim($column, '!');
            }

            // Dizi değerler için işlem
            if (is_array($value)) {
                // IN operatörü için dizi kontrolü
                if (isset($value['in']) && is_array($value['in']) && count($value['in']) > 0) {
                    $placeholders = [];
                    foreach ($value['in'] as $index => $item) {
                        $placeholder = ":{$column}_{$index}";
                        $placeholders[] = $placeholder;
                        $parameters[$placeholder] = $item;
                    }
                    $operator = $isNotCondition ? 'NOT IN' : 'IN';
                    $conditions[] = "`$column` $operator (" . implode(", ", $placeholders) . ")";
                } // Karşılaştırma operatörleri için kontrol
                else {
                    foreach ($value as $operator => $operandValue) {
                        if (isset($operators[$operator])) {
                            $placeholder = ":{$column}_" . count($parameters);
                            $conditions[] = "`$column` " . $operators[$operator] . " $placeholder";
                            $parameters[$placeholder] = $operandValue;
                        }
                    }
                }
            } // Basit eşitlik kontrolü
            else {
                $placeholder = ":{$column}";
                $operator = $isNotCondition ? '!=' : '=';
                $conditions[] = "`$column` $operator $placeholder";
                $parameters[$placeholder] = $value;
            }
        }

        $whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
    }
}