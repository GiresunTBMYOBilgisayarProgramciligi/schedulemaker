<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\ScheduleItemDTO;

/**
 * Schedule Item validator
 * 
 * Schedule item verilerini doğrular
 */
class ScheduleItemValidator extends BaseValidator
{
    /**
     * Schedule item verisini doğrular
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        // Zorunlu alanlar
        if (!isset($data['schedule_id']) || !is_numeric($data['schedule_id'])) {
            $errors['schedule_id'] = 'schedule_id gerekli ve sayısal olmalı';
        }

        if (!isset($data['day_index'])) {
            $errors['day_index'] = 'day_index gerekli';
        } elseif (!$this->isInRange($data['day_index'], 0, 6)) {
            $errors['day_index'] = 'day_index 0-6 arasında olmalı';
        }

        if (isset($data['week_index']) && $data['week_index'] < 0) {
            $errors['week_index'] = 'week_index negatif olamaz';
        }

        // Zaman validasyonu
        if (!isset($data['start_time'])) {
            $errors['start_time'] = 'start_time gerekli';
        } elseif (!$this->isValidTimeFormat($data['start_time'])) {
            $errors['start_time'] = 'start_time geçersiz format (HH:MM olmalı)';
        }

        if (!isset($data['end_time'])) {
            $errors['end_time'] = 'end_time gerekli';
        } elseif (!$this->isValidTimeFormat($data['end_time'])) {
            $errors['end_time'] = 'end_time geçersiz format (HH:MM olmalı)';
        }

        // Başlangıç < Bitiş kontrolü
        if (isset($data['start_time'], $data['end_time'])) {
            if ($data['start_time'] >= $data['end_time']) {
                $errors['start_time'] = 'Başlangıç saati bitiş saatinden küçük olmalı';
                $errors['end_time'] = 'Başlangıç saati bitiş saatinden küçük olmalı';
            }
        }

        // Status validasyonu
        $validStatuses = ['single', 'group', 'preferred', 'unavailable'];
        if (!isset($data['status'])) {
            $errors['status'] = 'status gerekli';
        } elseif (!in_array($data['status'], $validStatuses)) {
            $errors['status'] = 'status geçersiz (single, group, preferred, unavailable olmalı)';
        }

        // Data validasyonu (eğer single veya group ise)
        if (isset($data['status']) && in_array($data['status'], ['single', 'group'])) {
            if (empty($data['data'])) {
                $errors['data'] = 'single veya group item için data gerekli';
            } elseif (!is_array($data['data'])) {
                $errors['data'] = 'data array olmalı';
            } else {
                // ESKİ SİSTEM FORMAT: data ARRAY OF OBJECTS olmalı
                // Single: [{"lesson_id": "503", "lecturer_id": "158", ...}]
                // Group:  [{"lesson_id": "503", ...}, {"lesson_id": "504", ...}]

                // En az 1 eleman olmalı
                if (count($data['data']) === 0) {
                    $errors['data'] = 'data boş olamaz';
                } else {
                    // İlk elemanı kontrol et
                    if (!is_array($data['data'][0])) {
                        $errors['data'] = 'data[0] array olmalı (eski format: array of objects)';
                    } elseif (!isset($data['data'][0]['lesson_id'])) {
                        $errors['data'] = 'data[0] içinde lesson_id gerekli';
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return ScheduleItemDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ScheduleItemDTO
    {
        $this->validate($data);
        return ScheduleItemDTO::fromArray($data);
    }

    /**
     * Batch validasyon - birden fazla item'ı doğrular
     * @param array $itemsData
     * @return void
     * @throws ValidationException
     */
    public function validateBatch(array $itemsData): void
    {
        $allErrors = [];

        foreach ($itemsData as $index => $itemData) {
            try {
                $this->validate($itemData);
            } catch (ValidationException $e) {
                foreach ($e->getValidationErrors() as $error) {
                    $allErrors[] = "Item #{$index}: {$error}";
                }
            }
        }

        if (!empty($allErrors)) {
            throw new ValidationException('Toplu veri doğrulama hatası.', $allErrors);
        }
    }
    
    /**
     * Toplu veri doğrulaması yapar ve DTO array'i döner
     * @param array $itemsData
     * @return ScheduleItemDTO[]
     * @throws ValidationException
     */
    public function getBatchDTO(array $itemsData): array
    {
        $this->validateBatch($itemsData);
        return array_map(fn($itemData) => ScheduleItemDTO::fromArray($itemData), $itemsData);
    }
}
