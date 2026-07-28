<?php

namespace App\Validators;

use App\DTOs\ScheduleNoteDTO;
use App\DTOs\ScheduleNoteStatusDTO;
use App\Enums\ScheduleNoteStatus;
use App\Exceptions\ValidationException;

/**
 * ScheduleNote verilerini doğrulayan validator sınıfı.
 */
class ScheduleNoteValidator extends BaseValidator
{
    /**
     * Varsayılan doğrulama metodu (validateNote'u çağırır)
     */
    public function validate(array $data): void
    {
        $this->validateNote($data);
    }

    /**
     * Varsayılan DTO üretme metodu (getNoteDTO'yu çağırır)
     */
    public function getDTO(array $data): mixed
    {
        return $this->getNoteDTO($data);
    }

    /**
     * Akademisyen not girdisini doğrular.
     */
    public function validateNote(array $data): void
    {
        $errors = [];

        if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
            $errors['user_id'] = 'Kullanıcı ID gereklidir.';
        }

        if (empty($data['academic_year'])) {
            $errors['academic_year'] = 'Akademik yıl gereklidir.';
        }

        if (empty($data['semester']) || !in_array($data['semester'], ['Güz', 'Bahar', 'Yaz'], true)) {
            $errors['semester'] = 'Geçerli bir dönem seçilmelidir.';
        }

        $validTypes = ['lesson', 'midterm-exam', 'final-exam', 'makeup-exam'];
        if (empty($data['schedule_type']) || !in_array($data['schedule_type'], $validTypes, true)) {
            $errors['schedule_type'] = 'Geçerli bir program türü seçilmelidir.';
        }

        if (empty($data['note']) || trim($data['note']) === '') {
            $errors['note'] = 'Not içeriği boş olamaz.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Not doğrulama hatası.', $errors);
        }
    }

    /**
     * Düzenleyici durum güncelleme girdisini doğrular.
     */
    public function validateStatus(array $data): void
    {
        $errors = [];

        if (empty($data['note_id']) || !is_numeric($data['note_id'])) {
            $errors['note_id'] = 'Not ID gereklidir.';
        }

        if (empty($data['status']) || !ScheduleNoteStatus::tryFrom($data['status'])) {
            $errors['status'] = 'Geçerli bir işlem durumu seçilmelidir.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Durum güncelleme hatası.', $errors);
        }
    }

    public function getNoteDTO(array $data): ScheduleNoteDTO
    {
        $this->validateNote($data);
        return ScheduleNoteDTO::fromArray($data);
    }

    public function getStatusDTO(array $data): ScheduleNoteStatusDTO
    {
        $this->validateStatus($data);
        return ScheduleNoteStatusDTO::fromArray($data);
    }
}
