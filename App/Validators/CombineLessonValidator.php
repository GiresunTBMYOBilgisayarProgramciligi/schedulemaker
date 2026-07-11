<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\CombineLessonDTO;

class CombineLessonValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veriler
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if (empty($data['parent_lesson_id']) || !is_numeric($data['parent_lesson_id']) || $data['parent_lesson_id'] <= 0) {
            $errors['parent_lesson_id'] = 'Birleştirilecek üst ders belirtilmemiş veya geçersiz.';
        }

        if (empty($data['child_lesson_id']) || !is_numeric($data['child_lesson_id']) || $data['child_lesson_id'] <= 0) {
            $errors['child_lesson_id'] = 'Bağlanacak ders belirtilmemiş veya geçersiz.';
        }

        if (isset($data['items_to_remove']) && !is_array($data['items_to_remove'])) {
            $errors['items_to_remove'] = 'Kaldırılacak öğeler listesi geçerli formatta değil.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return CombineLessonDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): CombineLessonDTO
    {
        $this->validate($data);
        return CombineLessonDTO::fromArray($data);
    }
}
