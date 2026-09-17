<?php

namespace App\Validators;

use App\Enums\LessonType;
use App\Exceptions\ValidationException;
use App\DTOs\LessonDTO;

class LessonValidator extends BaseValidator
{
    /**
     * @var bool Hoca kendi dersini güncelliyorsa, bazı kontroller atlanır.
     */
    private bool $isLecturerSelfUpdate;

    /**
     * @var bool Ders atama ekranından güncelleme yapılıyorsa (sadece atanabilir alanlar doğrulanır).
     */
    private bool $isAssignmentUpdate;

    public function __construct(bool $isLecturerSelfUpdate = false, bool $isAssignmentUpdate = false)
    {
        $this->isLecturerSelfUpdate = $isLecturerSelfUpdate;
        $this->isAssignmentUpdate = $isAssignmentUpdate;
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

        if ($this->isAssignmentUpdate) {
            // Ders atama ekranı kontrolleri
            if (empty($data['id']) || !is_numeric($data['id']) || (int)$data['id'] <= 0) {
                $errors['id'] = 'Geçerli bir ders ID belirtilmelidir.';
            }
            if (isset($data['hours']) && $data['hours'] !== '' && (!is_numeric($data['hours']) || (int)$data['hours'] < 1)) {
                $errors['hours'] = 'Ders saati en az 1 olmalıdır.';
            }
            if (isset($data['semester_no']) && $data['semester_no'] !== '' && (!is_numeric($data['semester_no']) || (int)$data['semester_no'] < 1)) {
                $errors['semester_no'] = 'Geçerli bir yarıyıl numarası belirtilmelidir.';
            }
            if (isset($data['type']) && $data['type'] !== '' && LessonType::tryFrom((int)$data['type']) === null) {
                $errors['type'] = 'Geçersiz ders türü.';
            }
            if (!empty($data['lecturer_id']) && (!is_numeric($data['lecturer_id']) || (int)$data['lecturer_id'] < 1)) {
                $errors['lecturer_id'] = 'Geçerli bir hoca seçilmelidir.';
            }
            if (!empty($data['building_id']) && !is_numeric($data['building_id'])) {
                $errors['building_id'] = 'Bina ID değeri sayısal olmalıdır.';
            }
        } elseif (!$this->isLecturerSelfUpdate) {
            // Admin kontrolleri
            if (empty($data['lecturer_id']) || $data['lecturer_id'] == '0') {
                $errors['lecturer_id'] = 'Hoca bilgisi eksik yada hatalı.';
            }
            if (empty($data['department_id']) || $data['department_id'] == '0') {
                $errors['department_id'] = 'Bölüm bilgisi eksik yada hatalı.';
            }
            if (empty($data['program_id']) || $data['program_id'] == '0') {
                $errors['program_id'] = 'Program bilgisi eksik yada hatalı.';
            }
            if (empty($data['name'])) {
                $errors['name'] = 'Ders adı zorunludur.';
            }
            if (empty($data['code'])) {
                $errors['code'] = 'Ders kodu zorunludur.';
            }
            if (!isset($data['hours']) || $data['hours'] === '') {
                $errors['hours'] = 'Ders saati zorunludur.';
            } elseif (!is_numeric($data['hours'])) {
                $errors['hours'] = 'Ders saati geçerli bir sayı olmalıdır.';
            }
            if (isset($data['group_no']) && $data['group_no'] !== '' && !is_numeric($data['group_no'])) {
                $errors['group_no'] = 'Grup numarası geçerli bir sayı olmalıdır.';
            }
            if (isset($data['semester_no']) && $data['semester_no'] !== '' && !is_numeric($data['semester_no'])) {
                $errors['semester_no'] = 'Yarıyıl geçerli bir sayı olmalıdır.';
            }
            if (!isset($data['type']) || $data['type'] === '') {
                $errors['type'] = 'Ders türü zorunludur.';
            } elseif (LessonType::tryFrom((int)$data['type']) === null) {
                $errors['type'] = 'Geçersiz ders türü.';
            }
            if (!empty($data['building_id']) && !is_numeric($data['building_id'])) {
                $errors['building_id'] = 'Bina ID değeri sayısal olmalıdır.';
            }
        }

        // Genel kontroller (Hoca da değiştirebilir)
        if (!isset($data['size']) || $data['size'] === '') {
            $errors['size'] = 'Ders mevcudu zorunludur.';
        } elseif (!is_numeric($data['size']) || $data['size'] < 0) {
            $errors['size'] = 'Ders mevcudu geçerli bir sayı olmalıdır.';
        }

        if (isset($data['classroom_type']) && $data['classroom_type'] !== '' && !is_numeric($data['classroom_type'])) {
            $errors['classroom_type'] = 'Derslik türü geçerli bir değer olmalıdır.';
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
