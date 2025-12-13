<?php

namespace App\Helpers;

use Exception;
use InvalidArgumentException;

/**
 * Class FilterValidator
 *
 * Gelen ham filtre dizilerini alır, belirli bir işleme (operasyon) göre
 * doğrular, temizler ve sadece izin verilen, tipi doğrulanmış filtreleri döndürür.
 *
 * Kullanım:
 * $validator = new FilterValidator();
 * try {
 * $filters = $validator->validate($_GET, 'getScheduleList');
 * // $filters artık güvenli ve kullanılabilir.
 * } catch (\InvalidArgumentException $e) {
 * // Hata (örn: zorunlu alan eksik)
 * echo $e->getMessage();
 * }
 */
class FilterValidator
{
    /**
     * Master Şema.
     * Sistemde olması muhtemel TÜM filtre anahtarlarını ve bunların
     * veri türlerini tanımlar.
     *
     * @var array
     */
    private $masterSchema = [];

    /**
     * Operasyon Kuralları.
     * Hangi işlemin hangi filtrelere ihtiyacı olduğunu tanımlar.
     *
     * @var array
     */
    private $operationRules = [];
    public $schema = "";

    public function __construct()
    {

        // 1. Tüm olası filtreleri ve türlerini tanımla
        $this->masterSchema = [
            'type' => ['type' => 'string'],//Ders programı türünü belirtir (exam, lesson)
            'hours' => ['type' => 'int'],// Derslik kontrolü yapılırken kaç saat ekleneceği bilgisi
            'owner_type' => ['type' => 'string'],//Ders programının ait olduğu birimi belirtir (user, lesson, classroom, program)
            'owner_id' => ['type' => 'int'],//Ders programının ait olduğu birimin ID numarası
            'time' => ['type' => 'string'], // Dersin saat aralığı "10:00-10:50" formatında olabilir TODO buna ihtiyaç kalmayacak gibi görünüyor
            'semester_no' => ['type' => 'int|int[]'], //Dersin ait olduğu yarıyıl numarası 1 veya [1, 3]
            'semester' => ['type' => 'string'],//Ders programının ait olduğu dönem (Güz, Bahar)
            'academic_year' => ['type' => 'string'],//Ders programının ait olduğu akademik yıl (2024 - 2025)
            'day_index' => ['type' => 'int'],//Dersin gün index numarası (0 = Pazar, 1 = Pazartesi, ...)
            'lesson_hours' => ['type' => 'int'],//Dersin kaç saatlik olduğu
            'lesson_id' => ['type' => 'int'],//İşlem yapılacak ders id numarsı
            'classroom_id' => ['type' => 'int'],//Dersin yapılacağı dersliğin id numarası
            'lecturer_id' => ['type' => 'int'],//Ders programının hoca id numarası
            'day' => ['type' => 'array'],//Gün bilgisi içeren dizi (lesson_id, lecturer_id, classroom_id)
            'owners' => ['type' => 'array'], //Ders programının ait olduğu birim türleri listesi
            'schedule_id' => ['type' => 'int'],//Ders programının id numarası
            'startTime' => ['type' => 'string'],//Dersin başlangıç saati
        ];

        // 2. Her işlem için kuralları tanımla
        $this->operationRules = [
            'saveScheduleAction' => [
                'required' => ["type", "lesson_id", "classroom_id", "time", "lesson_hours", "day_index"],
                'optional' => ['lecturer_id'],
                'defaults' => ['semester', 'academic_year']
            ],
            'checkScheduleCrash' => [
                'required' => ["type", "lesson_id", "classroom_id", "time", "lesson_hours", "day_index"],
                'optional' => ["semester_no", "lecturer_id"],
                'defaults' => ['semester', 'academic_year']
            ],
            'deleteScheduleAction' => [
                'required' => ["type", "time"],
                'optional' => ['owner_type', "owner_id", "lesson_id", "classroom_id", "lecturer_id", "day_index"],
                'defaults' => ['semester', 'academic_year']
            ],
            'deleteSchedule' => [
                'required' => ["type", "time", "owner_type", "owner_id"],
                'optional' => ["semester_no", "day", "day_index", "classroom_id"],
                'defaults' => ['semester', 'academic_year']
            ],
            "checkAndDeleteSchedule" => [
                'required' => ["day_index"],
                'optional' => ["day"],
                'defaults' => ['semester', 'academic_year']
            ],
            "checkLecturerScheduleAction" => [
                'required' => ["type", "lesson_id"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "checkClassroomScheduleAction" => [
                'required' => ["type", "lesson_id"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "checkProgramScheduleAction" => [
                'required' => ["type", "lesson_id"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "getSchedulesHTML" => [
                'required' => ["type", "owner_type", "owner_id"],
                'optional' => ["semester_no"],
                'defaults' => ['semester', 'academic_year']
            ],
            "prepareScheduleCard" => [
                'required' => ["type", "owner_type", "owner_id","semester_no"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "availableLessons" => [
                'required' => ["type", "owner_type", "owner_id", "semester_no"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "prepareScheduleRows" => [
                'required' => ["type", "owner_type", "owner_id","semester_no"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            'createScheduleExcelTable' => [
                'required' => ['type', "owner_type"],
                'optional' => ['owner_id',],
                'defaults' => ['semester', 'academic_year']
            ],
            "exportScheduleAction" => [
                'required' => ["type", "owner_type"],
                'optional' => ["owner_id","semester_no"],
                'defaults' => ['semester', 'academic_year']
            ],
            "generateScheduleFilters"=> [
                'required' => ["type", "owner_type"],
                'optional' => ["owner_id","semester_no"],
                'defaults' => ['semester', 'academic_year']
            ],
            "exportSchedule" => [
                'required' => ["type", "owner_type"],
                'optional' => ["owner_id", "semester_no"],
                'defaults' => ['semester', 'academic_year']
            ],
            "availableClassrooms" => [
                'required' => ["schedule_id", 'hours', "startTime", "lesson_id", "day_index"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "availableObservers" => [
                'required' => ["type", 'hours', "time", "day_index"],
                'optional' => [],
                'defaults' => ['semester', 'academic_year']
            ],
            "saveSchedulePreferenceAction" => [
                'required' => ["type", "owner_type", "owner_id","time","day_index","day"],
                'optional' => ["semester_no"],
                'defaults' => ['semester', 'academic_year']
            ],
            "exportScheduleIcsAction" => [
                'required' => ["owner_type"],
                'optional' => ["semester_no","owner_id"],
                'defaults' => ['semester', 'academic_year',"type"]
            ]

        ];
    }

    /**
     * Ana doğrulama fonksiyonu.
     *
     * @param array $data Gelen ham veri (örn: $_POST veya $_GET)
     * @param string $for İşlemin adı (operationRules içindeki bir anahtar)
     * @return array Sadece izin verilen, doğrulanmış ve varsayılanları eklenmiş filtre dizisi.
     * @throws \InvalidArgumentException Zorunlu alanlar eksikse, boşsa veya tipi yanlışsa.
     * @throws \Exception Kural tanımı bulunamazsa.
     */
    public function validate(array $data, string $for): array
    {
        $this->schema = $for;
        if (!isset($this->operationRules[$for])) {
            throw new \Exception("'$for' için tanımlanmış bir doğrulama kuralı yok.");
        }

        $rules = $this->operationRules[$for];
        $validatedFilters = [];

        // Kural setlerini al
        $requiredKeys = $rules['required'] ?? [];
        $optionalKeys = $rules['optional'] ?? [];
        $defaultKeys = $rules['defaults'] ?? [];

        // 1. Zorunlu (Required) Filtreleri İşle
        foreach ($requiredKeys as $key) {
            // Anahtar yok mu?
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("'$for' işlemi için zorunlu filtre eksik: $key");
            }

            $value = $data[$key];

            // Değer boş mu? (null, '', "null" string'i)
            if ($value === null || $value === '' || $value === "null") {
                throw new \InvalidArgumentException("'$for' işlemi için zorunlu filtre ($key) boş olamaz.");
            }

            // Tipini doğrula
            $this->validateType($key, $value);
            $validatedFilters[$key] = $value;
        }

        // 2. Opsiyonel (Optional) Filtreleri İşle
        foreach ($optionalKeys as $key) {
            // Anahtar yoksa VEYA değeri boşsa, atla. (Çünkü opsiyonel)
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '' || $value === "null") {
                continue;
            }

            // Anahtar var ve doluysa, tipini doğrula
            $this->validateType($key, $value);
            $validatedFilters[$key] = $value;
        }

        // 3. Varsayılan (Default) Filtreleri İşle
        foreach ($defaultKeys as $key) {
            // Eğer kullanıcı bu filtre için dolu bir değer GÖNDERDİYSE, onu kullan.
            if (array_key_exists($key, $data)) {
                $value = $data[$key];

                // Kullanıcı boş yollamadıysa...
                if (!($value === null || $value === '' || $value === "null")) {
                    $this->validateType($key, $value);
                    $validatedFilters[$key] = $value;
                    continue; // Bir sonrakine geç (varsayılanı ez)
                }
            }

            // Kullanıcı bu filtreyi hiç yollamadıysa VEYA boş yolladıysa,
            // varsayılan değeri ata.
            // Bu kısmı kendi projenizdeki ayarlara göre düzenlemelisiniz.
            if ($key === 'semester') {
                $validatedFilters[$key] = getSettingValue('semester'); // 'getSettingValue' fonksiyonunuzu varsayıyorum
            } elseif ($key === 'academic_year') {
                $validatedFilters[$key] = getSettingValue('academic_year');
            } elseif ($key === 'type') {
                $validatedFilters[$key] = 'lesson';
            }
        }

        // Dönen $validatedFilters dizisi şunları içerir:
        // - Tüm 'required' alanlar (doğrulanmış)
        // - Varsa, 'optional' alanlar (doğrulanmış)
        // - 'default' alanlar (ya kullanıcıdan gelen doğrulanmış değer ya da varsayılan değer)
        // - Bunların DIŞINDA hiçbir şey içermez.
        return $validatedFilters;
    }

    /**
     * Bir değerin beklenen türle eşleşip eşleşmediğini doğrular.
     *
     * @param string $key Hata mesajı için anahtar adı.
     * @param mixed $value Doğrulanacak değer.
     * @throws \InvalidArgumentException Tip uyuşmazsa.
     * @throws \Exception Şema tanımı yoksa.
     */
    private function validateType(string $key, $value): void
    {
        if (!isset($this->masterSchema[$key])) {
            throw new \Exception("Master şemada '$key' için bir tip tanımı yok.");
        }

        $expectedType = $this->masterSchema[$key]['type'];
        $possibleTypes = explode('|', $expectedType);

        foreach ($possibleTypes as $type) {
            switch ($type) {
                case 'int':
                    if ($this->isIntegerish($value))
                        return;
                    break;
                case 'string':
                    if (is_string($value))
                        return;
                    break;
                case 'array':
                    if (is_array($value))
                        return;
                    break;
                case 'int[]':
                    if (is_array($value) && $this->isArrayOf($value, 'isIntegerish'))
                        return;
                    break;
                case 'string[]':
                    if (is_array($value) && $this->isArrayOf($value, 'is_string'))
                        return;
                    break;
            }
        }

        // Hiçbiri eşleşmedi
        $actualType = is_object($value) ? get_class($value) : gettype($value);
        throw new \InvalidArgumentException(
            "$this->schema işleminde '$key' filtresi için geçersiz veri türü. Beklenen: '$expectedType', Gelen: '$actualType'"
        );
    }

    /**
     * Bir değerin "integer-benzeri" olup olmadığını kontrol eder.
     * (örn: 123, "123", "0", 45.0)
     */
    private function isIntegerish($value): bool
    {
        return is_int($value) || (is_numeric($value) && (int) $value == $value);
    }

    /**
     * Bir dizinin tüm elemanlarının belirli bir türde olup olmadığını kontrol eder.
     *
     * @param array $array
     * @param string|callable $checkFunction 'is_string' gibi bir fonksiyon adı veya [$this, 'isIntegerish']
     * @return bool
     */
    private function isArrayOf(array $array, $checkFunction): bool
    {
        if (empty($array))
            return true; // Boş dizi geçerlidir.

        // $checkFunction 'isIntegerish' ise, onu $this objesiyle çağrılabilir hale getir
        if ($checkFunction === 'isIntegerish') {
            $checkFunction = [$this, 'isIntegerish'];
        }

        foreach ($array as $item) {
            if (!call_user_func($checkFunction, $item)) {
                return false;
            }
        }
        return true;
    }
}
