<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;

/**
 * Toplu işlem (bulk action) verilerini doğrulayan validator.
 *
 * Her entity için izin verilen alanları tanımlar ve
 * gelen verileri bu kurallara göre doğrular.
 */
class BulkActionValidator extends BaseValidator
{
    /**
     * Her entity için toplu düzenlemede izin verilen alanlar ve tipleri.
     * Tip değerleri: 'boolean', 'integer', 'string'
     */
    private const ALLOWED_FIELDS = [
        'program' => [
            'active' => 'boolean',
            'department_id' => 'integer',
        ],
        'lesson' => [
            'semester' => 'string',
            'academic_year' => 'string',
            'building_id' => 'integer',
            'lecturer_id' => 'integer',
        ],
        'user' => [
            'role' => 'string',
            'unit_id' => 'integer',
            'department_id' => 'integer',
            'program_id' => 'integer',
        ],
        'department' => [
            'active' => 'boolean',
            'unit_id' => 'integer',
        ],
        'unit' => [
            'active' => 'boolean',
        ],
        'classroom' => [
            'building_id' => 'integer',
        ],
        'building' => [
            'unit_id' => 'integer',
        ],
    ];

    /**
     * Genel veri doğrulaması (BaseValidator uyumluluğu için).
     * Toplu işlemlerde doğrudan kullanılmaz, entity spesifik metotlar tercih edilir.
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $this->validateIds($data);
    }

    /**
     * BaseValidator uyumluluğu için. Toplu işlemlerde kullanılmaz.
     *
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function getDTO(array $data): mixed
    {
        $this->validate($data);
        return BulkDeleteDTO::fromArray($data);
    }

    /**
     * ID dizisini doğrular.
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    private function validateIds(array $data): void
    {
        $errors = [];

        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            $errors['ids'] = 'En az bir kayıt seçilmelidir.';
        } else {
            foreach ($data['ids'] as $index => $id) {
                if (!is_numeric($id) || (int)$id <= 0) {
                    $errors['ids'] = 'Geçersiz kayıt ID\'si bulundu.';
                    break;
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Doğrulama hatası.', $errors);
        }
    }

    /**
     * Toplu silme verilerini doğrular ve DTO döndürür.
     *
     * @param array $data
     * @return BulkDeleteDTO
     * @throws ValidationException
     */
    public function getDeleteDTO(array $data): BulkDeleteDTO
    {
        $this->validateIds($data);
        return BulkDeleteDTO::fromArray($data);
    }

    /**
     * Toplu güncelleme verilerini doğrular ve DTO döndürür.
     *
     * @param array $data
     * @param string $entity Entity adı (program, lesson, user, vb.)
     * @return BulkUpdateDTO
     * @throws ValidationException
     */
    public function getUpdateDTO(array $data, string $entity): BulkUpdateDTO
    {
        $this->validateIds($data);
        $this->validateFields($data, $entity);
        return BulkUpdateDTO::fromArray($data);
    }

    /**
     * Güncelleme alanlarını entity'ye göre doğrular.
     *
     * @param array $data
     * @param string $entity
     * @return void
     * @throws ValidationException
     */
    private function validateFields(array $data, string $entity): void
    {
        $errors = [];

        if (!isset($data['fields']) || !is_array($data['fields']) || empty($data['fields'])) {
            $errors['fields'] = 'En az bir alan seçilmelidir.';
            throw new ValidationException('Doğrulama hatası.', $errors);
        }

        $allowedFields = self::ALLOWED_FIELDS[$entity] ?? [];

        if (empty($allowedFields)) {
            $errors['entity'] = "'{$entity}' için toplu düzenleme desteklenmiyor.";
            throw new ValidationException('Doğrulama hatası.', $errors);
        }

        foreach ($data['fields'] as $fieldName => $fieldValue) {
            if (!array_key_exists($fieldName, $allowedFields)) {
                $errors[$fieldName] = "'{$fieldName}' alanı toplu düzenleme için izin verilmiyor.";
                continue;
            }

            $expectedType = $allowedFields[$fieldName];
            if (!$this->isValidFieldType($fieldValue, $expectedType)) {
                $errors[$fieldName] = "'{$fieldName}' alanı geçerli bir {$expectedType} değeri olmalıdır.";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Doğrulama hatası.', $errors);
        }
    }

    /**
     * Alan değerinin beklenen tipte olup olmadığını kontrol eder.
     *
     * @param mixed $value
     * @param string $expectedType
     * @return bool
     */
    private function isValidFieldType(mixed $value, string $expectedType): bool
    {
        // null değer, nullable alanlar için kabul edilir (ör. building_id = null)
        if ($value === null || $value === '') {
            return true;
        }

        return match ($expectedType) {
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false', 'on', 'off'], true),
            'integer' => is_numeric($value) && (int)$value > 0,
            'string' => is_string($value),
            default => false,
        };
    }
}
