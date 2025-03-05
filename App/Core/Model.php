<?php

namespace App\Core;


use Exception;
use PDO;
use App\Controllers\ProgramController;

class Model
{
    protected string $table_name = "";
    private static ?PDO $database = null;
    protected ?string $whereClause = null;
    protected array $parameters = [];
    protected array $relations = [];
    protected array $orderBy = [];
    protected ?string $limit = null;
    protected ?string $offset = null;
    protected array $selectedFields = ['*'];

    public function __construct()
    {
        if (self::$database === null) {
            self::$database = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
        }
    }
    /*
     * Quey Builder
     */
    /**
     * Query builder'ı başlatır ve mevcut nesneyi döndürür
     * @return $this
     */
    public function get(): static
    {
        return $this;
    }

    /**
     * Seçilecek alanları belirler
     * @param array|string $fields Alan isimleri
     * @return $this
     */
    public function select(array|string $fields): static
    {
        if (is_string($fields)) {
            $this->selectedFields = explode(',', $fields);
            $this->selectedFields = array_map('trim', $this->selectedFields);
        } elseif (is_array($fields)) {
            $this->selectedFields = $fields;
        }

        return $this;
    }

    /**
     * SQL sorgularının WHERE kısmını dinamik olarak oluşturur.
     *
     * @param array|null $filters Filtre koşullarını içeren dizi. null ise boş WHERE clause döner
     * @param string $logicalOperator Koşullar arasında kullanılacak mantıksal operatör ("AND" veya "OR")
     * @return Model Method zincirlemesi için model nesnesini döner
     *
     * Desteklenen operatörler:
     * - Eşitlik: Direkt değer atama ile ('column' => 'value')
     * - Eşit Değil: Başına ! koyarak ('!column' => 'value')
     * - Büyüktür: 'column' => ['>' => value]
     * - Büyük Eşittir: 'column' => ['>=' => value]
     * - Küçüktür: 'column' => ['<' => value]
     * - Küçük Eşittir: 'column' => ['<=' => value]
     * - LIKE: 'column' => ['like' => '%value%']
     * - NOT LIKE: 'column' => ['!like' => '%value%']
     * - IN: 'column' => ['in' => [1, 2, 3]]
     * - NOT IN: '!column' => ['in' => [1, 2, 3]]
     *
     * @example
     * // Basit eşitlik kontrolü
     * $filters = ['status' => 'active'];
     * // Sonuç: `status` = :status
     *
     * @example
     * // Sayısal karşılaştırma
     * $filters = ['age' => ['>' => 18]];
     * // Sonuç: `age` > :age_0
     *
     * @example
     * // LIKE sorgusu
     * $filters = ['name' => ['like' => '%john%']];
     * // Sonuç: `name` LIKE :name_0
     *
     * @example
     * // IN operatörü
     * $filters = ['category_id' => ['in' => [1, 2, 3]]];
     * // Sonuç: `category_id` IN (:category_id_0, :category_id_1, :category_id_2)
     *
     * @example
     * // OR operatörü kullanımı
     * $model->where([
     *     'status' => 'active',
     *     'priority' => ['>' => 3]
     * ], "OR");
     * // Sonuç: `status` = :status OR `priority` > :priority_0
     *
     * @example
     * // AND ve OR operatörlerini birlikte kullanma
     * $model->where(['status' => 'pending'])
     *       ->where([
     *           'priority' => ['>' => 5],
     *           'category' => ['in' => [1, 2]]
     *       ], "OR");
     * // Sonuç: (`status` = :status) AND (`priority` > :priority_0 OR `category` IN (:category_0, :category_1))
     *
     * @example
     * // Karışık sorgular
     * $filters = [
     *     'age' => ['>' => 18],
     *     '!status' => 'inactive',
     *     'price' => ['<=' => 1000],
     *     'name' => ['like' => '%john%'],
     *     'tags' => ['in' => [1, 2, 3]],
     *     'priority' => ['>=' => 5]
     * ];
     * // Sonuç: `age` > :age_0 AND `status` != :status AND `price` <= :price_0
     * // AND `name` LIKE :name_0 AND `tags` IN (:tags_0, :tags_1, :tags_2) AND `priority` >= :priority_0
     */
    public function where(array $filters = null, string $logicalOperator = "AND"): static
    {
        if (is_null($filters)) {
            return $this;
        }
        // Eğer daha önce eklenmiş koşul var ise onun parantez içerisine alıp yenisini ekle
        if ($this->whereClause) {
            $this->whereClause = "(" . $this->whereClause . ")";
        }
        $conditions = [];
        $this->parameters = [];

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
                        $this->parameters[$placeholder] = $item;
                    }
                    $operator = $isNotCondition ? 'NOT IN' : 'IN';
                    $conditions[] = "`$column` $operator (" . implode(", ", $placeholders) . ")";
                } // Karşılaştırma operatörleri için kontrol
                else {
                    foreach ($value as $operator => $operandValue) {
                        if (isset($operators[$operator])) {
                            $placeholder = ":{$column}_" . count($this->parameters);
                            $conditions[] = "`$column` " . $operators[$operator] . " $placeholder";
                            $this->parameters[$placeholder] = $operandValue;
                        }
                    }
                }
            } // Basit eşitlik kontrolü
            else {
                $placeholder = ":{$column}";
                $operator = $isNotCondition ? '!=' : '=';
                $conditions[] = "`$column` $operator $placeholder";
                $this->parameters[$placeholder] = $value;
            }
        }
        $this->whereClause = count($conditions) > 0 ? implode(" " . $logicalOperator . " ", $conditions) : "";
        return $this;
    }

    /**
     * Sıralama ekler
     * @param string $column Sütun adı
     * @param string $direction Yön (ASC/DESC)
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    /**
     * Limit ekler
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset ekler
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * İlişkili modelleri ekler
     * @param array|string $relations İlişki isimleri
     * @return $this
     */
    public function with(array|string $relations): static
    {
        if (is_string($relations)) {
            $this->relations[] = $relations;
        } elseif (is_array($relations)) {
            $this->relations = array_merge($this->relations, $relations);
        }
        return $this;
    }

    /**
     * SQL sorgusunu oluşturur
     * @return array SQL sorgusu ve parametreler
     */
    protected function buildQuery(): array
    {
        $fields = implode(', ', $this->selectedFields);
        $sql = "SELECT {$fields} FROM {$this->table_name}";

        // WHERE şartları
        if ($this->whereClause) {
            $sql .= " WHERE " . $this->whereClause;
        }

        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY ";
            $orderByStatements = [];

            foreach ($this->orderBy as $order) {
                $orderByStatements[] = "{$order['column']} {$order['direction']}";
            }

            $sql .= implode(', ', $orderByStatements);
        }

        // LIMIT ve OFFSET
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";

            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return [
            'sql' => $sql,
            'parameters' => $this->parameters
        ];
    }

    /**
     * İlişkili verileri yükler
     * @param array $results Ana sorgu sonuçları
     * @return array İlişkili verilerle birleştirilmiş sonuçlar
     */
    protected function loadRelations(array $results): array
    {
        //todo
        // Bu fonksiyon implementasyonu veritabanı yapınıza göre değişecektir
        // Burada sadece temel yapı verilmiştir

        foreach ($this->relations as $relation) {
            // İlişki tipine göre yükleme işlemi
            // Örnek: hasMany, belongsTo vb.

            $relationMethod = "get" . ucfirst($relation) . "Relation";
            if (method_exists($this, $relationMethod)) {
                $results = $this->$relationMethod($results);
            }
        }

        return $results;
    }

    /**
     * Tüm sonuçları döndürür
     * @return array
     * @throws Exception
     */
    public function all(): array
    {
        $query = $this->buildQuery();
        $statement = self::$database->prepare($query['sql']);
        $statement->execute($query['parameters']);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        // İlişkili verileri yükle //todo
        if (!empty($this->relations) && !empty($results)) {
            $results = $this->loadRelations($results);
        }
        $models = [];
        foreach ($results as $result) {
            $model = new $this();
            $model->fill($result);
            $models[] = $model;
        }

        return $models;
    }

    /**
     * İlk sonucu döndürür
     * @return object|null
     * @throws Exception
     */
    public function first(): ?static
    {
        $this->limit(1);
        $result = $this->all();
        return !empty($result) ? $result[0] : null;
    }

    /**
     * ID'ye göre kayıt bulur
     * @param int $id
     * @return object|null
     * @throws Exception
     */
    public function find(int $id): ?object
    {
        return $this->where(['id' => $id])->first();
    }

    public function is_data_serialized($data): bool
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

    /**
     * @param array $data
     * @return void
     * @throws Exception
     */
    public function fill(array $data = []): void
    {
        // ReflectionClass ile alt sınıfın özelliklerini alın
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            //var_dump("$propertyName:",$propertyName);
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
        if (property_exists($this, 'department_id')) {
            if (is_null($this->department_id)) {
                $list = [(object)["id" => 0, "name" => "Program Seçiniz"]];
            } else {
                $list = (new ProgramController())->getProgramsList($this->department_id);
                array_unshift($list, (object)["id" => 0, "name" => "Program Seçiniz"]);
            }
        } else $list = [];
        return $list;
    }

    /**
     * Modelin tüm public özelliklerini döner, null olanları ve istenmeyen alanları hariç tutar.
     * @param array $excludedProperties Hariç tutulacak özellikler
     * @param bool $acceptNull
     * @return array
     */
    public function getArray(array $excludedProperties = ['table_name', 'database'], bool $acceptNull = false): array
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

    /*
    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO {$this->table_name} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $statement = self::$database->prepare($sql);
        foreach ($data as $field => $value) {
            $statement->bindValue(":{$field}", $value);
        }

        $statement->execute();
        return self::$database->lastInsertId();
    }


    public function update(int $id, array $data): bool
    {
        $setStatements = array_map(function($field) {
            return "{$field} = :{$field}";
        }, array_keys($data));

        $sql = "UPDATE {$this->table_name} SET " . implode(', ', $setStatements) . " WHERE id = :id";

        $statement = self::$database->prepare($sql);
        $statement->bindValue(':id', $id);

        foreach ($data as $field => $value) {
            $statement->bindValue(":{$field}", $value);
        }

        return $statement->execute();
    }*/

    /**
     * Kayıt silme
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'table_name') and !property_exists($this, 'id') ) {
            throw new Exception('Model düzgün oluşturulmamış');
        }

        if ($this->table_name == "users" and $this->id == 1) {
            throw new Exception("Birincil yönetici hesabı silinemez.");
        }

        $statement = self::$database->prepare("DELETE FROM {$this->table_name} WHERE id = :id");
        $statement->bindValue(':id', $this->id);
        if (!$statement->execute()) {
            throw new Exception('Kayıt bulunamadı veya silinemedi.');
        } else return true;

    }

    /**
     * Kayıt sayısını döndürür
     * @return int
     */
    public function count(): int
    {
        $query = $this->buildQuery();
        $sql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) as count FROM', $query['sql']);

        // LIMIT ve OFFSET'i kaldır
        $sql = preg_replace('/LIMIT \d+( OFFSET \d+)?/', '', $sql);

        $statement = self::$database->prepare($sql);
        $statement->execute($query['parameters']);
        $result = $statement->fetch(PDO::FETCH_OBJ);

        return isset($result->count) ? (int)$result->count : 0;
    }

    public function sum(string $column): float
    {
        $query = $this->buildQuery();
        $sql = preg_replace('/SELECT .* FROM/', "SELECT SUM($column) as total FROM", $query['sql']);

        // LIMIT ve OFFSET'i kaldır
        $sql = preg_replace('/LIMIT \d+( OFFSET \d+)?/', '', $sql);
        $statement = self::$database->prepare($sql);
        $statement->execute($query['parameters']);
        $result = $statement->fetch(PDO::FETCH_OBJ);

        return isset($result->total) ? (float)$result->total : 0.0;
    }
}