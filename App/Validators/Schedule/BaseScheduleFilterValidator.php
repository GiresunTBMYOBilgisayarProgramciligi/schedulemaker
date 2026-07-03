<?php

namespace App\Validators\Schedule;

use App\Validators\BaseValidator;
use App\Exceptions\ValidationException;
use App\DTOs\ScheduleFilterDTO;
use function App\Helpers\getSettingValue;

/**
 * Schedule filtre doğrulama işlemleri için temel sınıf
 * 
 * Tüm schedule filtre validator'ları bu sınıftan türer.
 * Master şema (alan tip tanımları), varsayılan değer atama ve
 * tip doğrulama gibi ortak mantığı barındırır.
 * 
 * Diğer BaseValidator sınıfından farkı: Bu sınıf entity verisi değil,
 * istek filtrelerini (request parameters) doğrular.
 */
abstract class BaseScheduleFilterValidator extends BaseValidator
{
    /**
     * Master Şema.
     * Sistemde olması muhtemel TÜM schedule filtre anahtarlarını ve
     * bunların veri türlerini tanımlar.
     */
    protected array $masterSchema = [
        'type'          => ['type' => 'string'],
        'hours'         => ['type' => 'int'],
        'owner_type'    => ['type' => 'string'],
        'owner_id'      => ['type' => 'int'],
        'time'          => ['type' => 'string'],
        'semester_no'   => ['type' => 'int|int[]'],
        'semester'      => ['type' => 'string'],
        'academic_year' => ['type' => 'string'],
        'day_index'     => ['type' => 'int'],
        'lesson_hours'  => ['type' => 'int'],
        'lesson_id'     => ['type' => 'int'],
        'classroom_id'  => ['type' => 'int'],
        'lecturer_id'   => ['type' => 'int'],
        'day'           => ['type' => 'array'],
        'owners'        => ['type' => 'array'],
        'schedule_id'   => ['type' => 'int'],
        'startTime'     => ['type' => 'string'],
        'items'         => ['type' => 'string'],
        'week_index'    => ['type' => 'int'],
        'show_code'     => ['type' => 'bool|int'],
        'show_lecturer' => ['type' => 'bool|int'],
        'show_program'  => ['type' => 'bool|int'],
        'show_observer' => ['type' => 'bool|int'],
        'start_time'    => ['type' => 'string'],
        'end_time'      => ['type' => 'string'],
    ];

    /**
     * Alt sınıflar tarafından implemente edilecek operasyon kuralları
     * 
     * @return array<string, array{required: string[], optional: string[], defaults: string[]}>
     */
    abstract protected function getOperationRules(): array;

    /**
     * BaseValidator'ın zorunlu validate metodu.
     * Alt sınıflar genellikle validateFor() metodunu kullanır.
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        // Operasyon kuralları arasından ilk kuralı kullan (veya alt sınıf override etsin)
        $rules = $this->getOperationRules();
        $firstOperation = array_key_first($rules);

        if ($firstOperation === null) {
            throw new ValidationException('Tanımlı operasyon kuralı bulunamadı.');
        }

        $this->validateFor($data, $firstOperation);
    }

    /**
     * Belirtilen operasyon için filtre doğrulaması yapar.
     * 
     * @param array $data Ham filtre verisi
     * @param string $operation Operasyon adı
     * @return void
     * @throws ValidationException
     */
    public function validateFor(array $data, string $operation): void
    {
        $rules = $this->getOperationRules();

        if (!isset($rules[$operation])) {
            throw new ValidationException("'$operation' için tanımlanmış bir doğrulama kuralı yok.");
        }

        $rule = $rules[$operation];
        $errors = [];

        $requiredKeys = $rule['required'] ?? [];
        $optionalKeys = $rule['optional'] ?? [];
        $defaultKeys  = $rule['defaults'] ?? [];

        // 1. Zorunlu filtreleri kontrol et
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[$key] = "'$operation' işlemi için zorunlu filtre eksik: $key";
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '' || $value === "null") {
                $errors[$key] = "'$operation' işlemi için zorunlu filtre ($key) boş olamaz.";
                continue;
            }

            $typeError = $this->validateFieldType($key, $value, $operation);
            if ($typeError !== null) {
                $errors[$key] = $typeError;
            }
        }

        // 2. Opsiyonel filtreleri kontrol et
        foreach ($optionalKeys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '' || $value === "null") {
                continue;
            }

            $typeError = $this->validateFieldType($key, $value, $operation);
            if ($typeError !== null) {
                $errors[$key] = $typeError;
            }
        }

        // 3. Varsayılan filtreleri kontrol et (hata yoksa)
        foreach ($defaultKeys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (!($value === null || $value === '' || $value === "null")) {
                    $typeError = $this->validateFieldType($key, $value, $operation);
                    if ($typeError !== null) {
                        $errors[$key] = $typeError;
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası', $errors);
        }
    }

    /**
     * Doğrulanmış ve temizlenmiş filtre dizisini döndürür.
     * 
     * Eski FilterValidator::validate() ile aynı davranışı sağlar:
     * - Zorunlu alanları kontrol eder
     * - Opsiyonel alanları dahil eder
     * - Varsayılan değerleri atar
     * - Sadece izin verilen alanları döndürür
     * 
     * @param array $data Ham filtre verisi
     * @param string $operation Operasyon adı
     * @return array Temizlenmiş filtre dizisi
     * @throws ValidationException Doğrulama hatası
     */
    public function sanitize(array $data, string $operation): array
    {
        $rules = $this->getOperationRules();

        if (!isset($rules[$operation])) {
            throw new ValidationException("'$operation' için tanımlanmış bir doğrulama kuralı yok.");
        }

        $rule = $rules[$operation];
        $validatedFilters = [];
        $errors = [];

        $requiredKeys = $rule['required'] ?? [];
        $optionalKeys = $rule['optional'] ?? [];
        $defaultKeys  = $rule['defaults'] ?? [];

        // 1. Zorunlu filtreleri işle
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[$key] = "'$operation' işlemi için zorunlu filtre eksik: $key";
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '' || $value === "null") {
                $errors[$key] = "'$operation' işlemi için zorunlu filtre ($key) boş olamaz.";
                continue;
            }

            try {
                $this->assertFieldType($key, $value, $operation);
                $validatedFilters[$key] = $value;
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        // 2. Opsiyonel filtreleri işle
        foreach ($optionalKeys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if ($value === null || $value === '' || $value === "null") {
                continue;
            }

            try {
                $this->assertFieldType($key, $value, $operation);
                $validatedFilters[$key] = $value;
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        // 3. Varsayılan filtreleri işle
        foreach ($defaultKeys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (!($value === null || $value === '' || $value === "null")) {
                    try {
                        $this->assertFieldType($key, $value, $operation);
                        $validatedFilters[$key] = $value;
                    } catch (\InvalidArgumentException $e) {
                        $errors[$key] = $e->getMessage();
                    }
                    continue;
                }
            }

            // Varsayılan değerleri ata
            $validatedFilters[$key] = $this->getDefaultValue($key);
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası', $errors);
        }

        return $validatedFilters;
    }

    /**
     * Gelen veriyi sanitize edip ScheduleFilterDTO döndürür.
     * @param array $data
     * @param string $operation
     * @return mixed
     * @throws ValidationException
     */
    public function getDTO(array $data, string $operation = 'view'): mixed
    {
        $sanitizedData = $this->sanitize($data, $operation);
        return ScheduleFilterDTO::fromArray($sanitizedData);
    }

    /**
     * Varsayılan değer döndürür.
     * Alt sınıflar ek varsayılanlar ekleyebilir.
     */
    protected function getDefaultValue(string $key): mixed
    {
        return match ($key) {
            'semester'      => getSettingValue('semester'),
            'academic_year' => getSettingValue('academic_year'),
            'type'          => 'lesson',
            'week_index'    => 0,
            default         => null,
        };
    }

    // ======================== Tip Doğrulama ========================

    /**
     * Alanın tip doğrulamasını yapar, hata mesajı döner (null = geçerli)
     */
    protected function validateFieldType(string $key, mixed $value, string $operation): ?string
    {
        if (!isset($this->masterSchema[$key])) {
            return "Master şemada '$key' için bir tip tanımı yok.";
        }

        $expectedType = $this->masterSchema[$key]['type'];
        if ($this->matchesType($value, $expectedType)) {
            return null;
        }

        $actualType = is_object($value) ? get_class($value) : gettype($value);
        return "$operation işleminde '$key' filtresi için geçersiz veri türü. Beklenen: '$expectedType', Gelen: '$actualType'";
    }

    /**
     * Tip doğrulaması yapar, hata varsa exception fırlatır
     */
    protected function assertFieldType(string $key, mixed $value, string $operation): void
    {
        $error = $this->validateFieldType($key, $value, $operation);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }
    }

    /**
     * Değerin beklenen tiple eşleşip eşleşmediğini kontrol eder
     */
    private function matchesType(mixed $value, string $expectedType): bool
    {
        $possibleTypes = explode('|', $expectedType);

        foreach ($possibleTypes as $type) {
            $matches = match ($type) {
                'int'      => $this->isIntegerish($value),
                'string'   => is_string($value),
                'array'    => is_array($value),
                'int[]'    => is_array($value) && $this->isArrayOfType($value, 'int'),
                'string[]' => is_array($value) && $this->isArrayOfType($value, 'string'),
                'bool'     => is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true),
                default    => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bir değerin "integer-benzeri" olup olmadığını kontrol eder
     */
    private function isIntegerish(mixed $value): bool
    {
        return is_int($value) || (is_numeric($value) && (int) $value == $value);
    }

    /**
     * Bir dizinin tüm elemanlarının belirli türde olup olmadığını kontrol eder
     */
    private function isArrayOfType(array $array, string $type): bool
    {
        if (empty($array)) {
            return true;
        }

        foreach ($array as $item) {
            $valid = match ($type) {
                'int'    => $this->isIntegerish($item),
                'string' => is_string($item),
                default  => false,
            };

            if (!$valid) {
                return false;
            }
        }

        return true;
    }
}
