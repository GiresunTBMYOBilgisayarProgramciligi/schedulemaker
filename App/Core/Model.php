<?php

namespace App\Core;


use Exception;
use PDO;
use App\Controllers\ProgramController;
use Monolog\Logger;

class Model
{
    protected string $table_name = "";
    public ?int $id = null;
    private static ?PDO $database = null;
    protected ?string $whereClause = null;
    protected array $parameters = [];
    protected array $relations = [];
    protected array $orderBy = [];
    protected ?string $limit = null;
    protected ?string $offset = null;
    protected array $selectedFields = ['*'];
    protected array $excludeFromDb = [];

    public function __construct()
    {
        if (self::$database === null) {
            self::$database = Database::getConnection();
        }
    }
    /**
     * Shared application logger for all models.
     */
    protected function logger(): Logger
    {
        return Log::logger();
    }

    /**
     * Standard logging context used across models.
     * Adds current user, caller, URL, IP and table name.
     */
    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    /**
     * Modelin Türkçe adını döner (örn: ders, kullanıcı, derslik)
     */
    public function getLabel(): string
    {
        return $this->table_name;
    }

    /**
     * Log mesajında gösterilecek detayı döner (örn: [BM101] Ders Adı)
     */
    public function getLogDetail(): string
    {
        return $this->id ? "ID: " . $this->id : "";
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
    public function where(?array $filters = null, string $logicalOperator = "AND"): static
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
                } elseif (isset($value['between']) && is_array($value['between']) && count($value['between']) == 2) {
                    $placeholder1 = ":{$column}_min_" . count($this->parameters);
                    $placeholder2 = ":{$column}_max_" . count($this->parameters);
                    $operator = $isNotCondition ? 'NOT BETWEEN' : 'BETWEEN';
                    $conditions[] = "`$column` $operator $placeholder1 AND $placeholder2";
                    $this->parameters[$placeholder1] = $value['between'][0];
                    $this->parameters[$placeholder2] = $value['between'][1];
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
                if (is_null($value)) {
                    $operator = $isNotCondition ? 'IS NOT' : 'IS';
                    $conditions[] = "`$column` $operator NULL";
                } else {
                    $placeholder = ":{$column}";
                    $operator = $isNotCondition ? '!=' : '=';
                    $conditions[] = "`$column` $operator $placeholder";
                    $this->parameters[$placeholder] = $value;
                }
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
            $this->relations[$relations] = [];
        } elseif (is_array($relations)) {
            foreach ($relations as $key => $value) {
                if (is_int($key)) {
                    // ['relationName'] format
                    $this->relations[$value] = [];
                } else {
                    // ['relationName' => ['option' => 'value']] format
                    $this->relations[$key] = $value;
                }
            }
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
     * @param array $results Ana sorgu sonuçları [ ['id'=>1, ...], ['id'=>2, ...] ]
     * @return array İlişkili verilerle birleştirilmiş sonuçlar
     */
    protected function loadRelations(array $results): array
    {
        // $this->relations structure: ['relationName' => ['options'], 'otherRelation' => []]
        foreach ($this->relations as $relation => $options) {

            // İlişki metodu ismi oluşturuluyor. Örn: 'items' -> 'getItemsRelation'
            $relationMethod = "get" . ucfirst($relation) . "Relation";

            if (method_exists($this, $relationMethod)) {
                // Metot varsa çalıştırılır ve $results dizisi güncellenip döner.
                // Bu metot, sonuç dizisine ilgili ilişkiyi 'key' olarak eklemelidir.
                // Options parametresi eklendi
                $results = $this->$relationMethod($results, $options);
            } else {
                // Geliştirme aşamasında hata ayıklamak için log düşülebilir
                if ($_ENV['DEBUG'] ?? false) {
                    $this->logger()->error("Model ilişkisi bulunamadı: " . get_class($this) . "::" . $relationMethod, $this->logContext());
                }
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
        if ($this->selectedFields != ['*']) {
            return $results;
        } else {
            $models = [];
            foreach ($results as $result) {
                $model = new $this();
                $model->fill($result);
                $models[] = $model;
            }

            return $models;
        }

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
    public function find($id): ?object
    {
        if (is_null($id)) {
            if (($_ENV['DEBUG']))
                error_log("Find metoduna id girilmemiş");
            return null;
        }

        $model = $this->where(['id' => $id])->first();
        return $model;
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
                if (isset($data[$propertyName])) {
                    $data[$propertyName] = $this->is_data_serialized($data[$propertyName]) ? unserialize($data[$propertyName]) : $data[$propertyName];
                    $this->$propertyName = $data[$propertyName] ?? $this->$propertyName;
                }
            }
        }
    }

    /**
     * todo bu hiç buraya ait durmuyor.
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
                $list = [(object) ["id" => 0, "name" => "Program Seçiniz"]];
            } else {
                $list = (new ProgramController())->getProgramsList(['department_id' => $this->department_id]);
                array_unshift($list, (object) ["id" => 0, "name" => "Program Seçiniz"]);
            }
        } else
            $list = [];
        return $list;
    }

    /**
     * Modelin tüm public özelliklerini döner, null olanları ve istenmeyen alanları $acceptNull değişkenine göre hariç tutar.
     * @param array $excludedProperties Hariç tutulacak özellikler
     * @param bool $acceptNull
     * @return array
     */
    public function getArray(array $excludedProperties = [], bool $acceptNull = false): array
    {
        // Exclude edilenleri birleştir
        $excludedProperties = array_merge($excludedProperties, $this->excludeFromDb);

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


    /**
     * @throws Exception
     */
    public function create(): void
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (empty($this->table_name)) {
            throw new Exception('Model düzgün oluşturulmamış: Tablo adı eksik.');
        }
        $data = $this->getArray(['id', "register_date", "last_login"]);
        //dizi türündeki veriler serialize ediliyor. DateTime nesneleri string'e çevriliyor.
        array_walk($data, function (&$value) {
            if (is_array($value)) {
                $value = serialize($value);
            } elseif ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
        });
        $fields = array_keys($data);
        // alanları güvenli hale getir (backtick ekle)
        $escapedFields = array_map(function ($field) {
            return "`{$field}`";
        }, $fields);
        $placeholders = array_map(function ($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO {$this->table_name} (" . implode(', ', $escapedFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $statement = self::$database->prepare($sql);
        foreach ($data as $field => $value) {
            $statement->bindValue(":{$field}", $value);
        }

        if ($statement->execute()) {
            $this->id = self::$database->lastInsertId();
            $this->logger()->debug("Yeni " . $this->getLabel() . " ekledi: " . $this->getLogDetail(), $this->logContext([$this]));
        }
    }


    /**
     * @throws Exception
     */
    public function update(array $additionalExclusions = [], bool $acceptNull = true): bool
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (empty($this->table_name) || empty($this->id)) {
            throw new Exception('Model düzgün oluşturulmamış: ID veya Tablo adı eksik.');
        }
        $data = $this->getArray(array_merge(['id'], $additionalExclusions), $acceptNull);

        // dizi türündeki veriler serialize ediliyor. DateTime nesneleri string'e çevriliyor.
        array_walk($data, function (&$value) {
            if (is_array($value)) {
                $value = serialize($value);
            } elseif ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
        });

        $setStatements = array_map(function ($field) {
            return "`{$field}`" . " = :{$field}";
        }, array_keys($data));

        $sql = "UPDATE `{$this->table_name}` SET " . implode(', ', $setStatements) . " WHERE `id` = :id";

        $statement = self::$database->prepare($sql);
        $statement->bindValue(':id', $this->id);

        foreach ($data as $field => $value) {
            $statement->bindValue(":{$field}", $value);
        }
        $this->logger()->debug($this->getLabel() . " güncellendi: " . $this->getLogDetail(), $this->logContext());
        return $statement->execute();
    }

    /**
     * Veritabanından kaydı siler.
     * Silme işlemi transaction (işlem) içerisine alınmıştır.
     * Silme öncesinde beforeDelete() hook'u çağrılır.
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (empty($this->table_name)) {
            throw new Exception('Model düzgün oluşturulmamış: Tablo adı eksik.');
        }

        if ($this->table_name == "users" and $this->id == 1) {
            throw new Exception("Birincil yönetici hesabı silinemez.");
        }

        $isInitiator = !self::$database->inTransaction();
        if ($isInitiator) {
            self::$database->beginTransaction();
        }

        try {
            // Silme işlemi öncesinde hook'u çalıştır (İlişkili veriler silinebilir)
            $this->beforeDelete();

            if ($this->id) {
                $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
                $statement = self::$database->prepare($sql);
                $statement->bindValue(':id', $this->id);
            } elseif ($this->whereClause) {
                $sql = "DELETE FROM {$this->table_name} WHERE " . $this->whereClause;
                $statement = self::$database->prepare($sql);
                // Parametreleri bağla
                foreach ($this->parameters as $key => $value) {
                    $statement->bindValue($key, $value);
                }
            } else {
                throw new Exception('Silinecek kayıt belirtilmemiş. ID yok veya where koşulu sağlanmamış.');
            }

            if (!$statement->execute()) {
                throw new Exception('Kayıt bulunamadı veya silinemedi.');
            }

            $this->logger()->debug($this->getLabel() . " silindi: " . $this->getLogDetail(), $this->logContext(['statement' => $statement]));

            if ($isInitiator) {
                self::$database->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($isInitiator) {
                self::$database->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Silme işlemi öncesinde çalıştırılacak hook.
     * Alt sınıflar bu metodu override ederek silme öncesi işlemlerini (ilişkili veri temizliği vb.) yapabilir.
     * @return void
     */
    protected function beforeDelete(): void
    {
        // Varsayılan olarak bir şey yapmaz.
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

        return isset($result->count) ? (int) $result->count : 0;
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

        return isset($result->total) ? (float) $result->total : 0.0;
    }
}