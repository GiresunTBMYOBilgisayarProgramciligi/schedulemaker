<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\LessonDTO;

class LessonValidator extends BaseValidator
{
    /**
     * @var bool Hoca kendi dersini güncelliyorsa, bazı kontroller atlanır.
     */
    private bool $isLecturerSelfUpdate;

    public function __construct(bool $isLecturerSelfUpdate = false)
    {
        $this->isLecturerSelfUpdate = $isLecturerSelfUpdate;
    }

    /**
     * Ders verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if (!$this->isLecturerSelfUpdate) {
            // Admin kontrolleri
            if (empty($data['lecturer_id']) || $data['lecturer_id'] == '0') {
                $errors[] = 'Hoca seçmelisiniz.';
            }
            if (empty($data['department_id']) || $data['department_id'] == '0') {
                $errors[] = 'Bölüm seçmelisiniz.';
            }
            if (empty($data['program_id']) || $data['program_id'] == '0') {
                $errors[] = 'Program seçmelisiniz.';
            }
            if (empty($data['name'])) {
                $errors[] = 'Ders adı zorunludur.';
            }
            if (empty($data['code'])) {
                $errors[] = 'Ders kodu zorunludur.';
            }
            if (!isset($data['hours']) || $data['hours'] === '') {
                $errors[] = 'Ders saati zorunludur.';
            }
        }

        // Genel kontroller (Hoca da değiştirebilir)
        if (!isset($data['size']) || $data['size'] === '') {
            $errors[] = 'Ders mevcudu zorunludur.';
        } elseif (!is_numeric($data['size']) || $data['size'] < 0) {
            $errors[] = 'Ders mevcudu geçerli bir sayı olmalıdır.';
        }

        if (isset($data['classroom_type']) && $data['classroom_type'] !== '' && !is_numeric($data['classroom_type'])) {
            $errors[] = 'Sınıf türü geçerli bir sayı olmalıdır.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return LessonDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): LessonDTO
    {
        $this->validate($data);
        return LessonDTO::fromArray($data);
    }
}
