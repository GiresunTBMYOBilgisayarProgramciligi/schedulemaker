<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\ToggleLockScheduleItemDTO;

class ToggleLockScheduleItemValidator extends BaseValidator
{
    /**
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        $ids = [];
        if (!empty($data['ids'])) {
            $ids = is_string($data['ids']) ? json_decode($data['ids'], true) : $data['ids'];
        } elseif (!empty($data['id'])) {
            $ids = [$data['id']];
        }

        if (empty($ids) || !is_array($ids)) {
            $errors['ids'] = 'Kilitlenecek/kilidi açılacak öğe ID\'leri (ids) belirtilmedi veya geçersiz.';
        } else {
            foreach ($ids as $index => $id) {
                if (!is_numeric($id)) {
                    $errors["ids[$index]"] = 'ID sayısal olmalıdır.';
                }
            }
        }

        if (isset($data['target_state']) && !in_array($data['target_state'], [0, 1, '0', '1', true, false], true)) {
            $errors['target_state'] = 'target_state sadece boolean değer alabilir.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * @param array $data
     * @return ToggleLockScheduleItemDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ToggleLockScheduleItemDTO
    {
        $this->validate($data);
        return ToggleLockScheduleItemDTO::fromArray($data);
    }
}
