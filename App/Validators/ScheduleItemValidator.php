<?php

namespace App\Validators;

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
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Zorunlu alanlar
        if (!isset($data['schedule_id']) || !is_numeric($data['schedule_id'])) {
            $errors[] = 'schedule_id gerekli ve sayısal olmalı';
        }

        if (!isset($data['day_index'])) {
            $errors[] = 'day_index gerekli';
        } elseif (!$this->isInRange($data['day_index'], 0, 6)) {
            $errors[] = 'day_index 0-6 arasında olmalı';
        }

        if (isset($data['week_index']) && $data['week_index'] < 0) {
            $errors[] = 'week_index negatif olamaz';
        }

        // Zaman validasyonu
        if (!isset($data['start_time'])) {
            $errors[] = 'start_time gerekli';
        } elseif (!$this->isValidTimeFormat($data['start_time'])) {
            $errors[] = 'start_time geçersiz format (HH:MM olmalı)';
        }

        if (!isset($data['end_time'])) {
            $errors[] = 'end_time gerekli';
        } elseif (!$this->isValidTimeFormat($data['end_time'])) {
            $errors[] = 'end_time geçersiz format (HH:MM olmalı)';
        }

        // Başlangıç < Bitiş kontrolü
        if (isset($data['start_time'], $data['end_time'])) {
            if ($data['start_time'] >= $data['end_time']) {
                $errors[] = 'Başlangıç saati bitiş saatinden küçük olmalı';
            }
        }

        // Status validasyonu
        $validStatuses = ['single', 'group', 'preferred', 'unavailable'];
        if (!isset($data['status'])) {
            $errors[] = 'status gerekli';
        } elseif (!in_array($data['status'], $validStatuses)) {
            $errors[] = 'status geçersiz (single, group, preferred, unavailable olmalı)';
        }

        // Data validasyonu (eğer single veya group ise)
        if (isset($data['status']) && in_array($data['status'], ['single', 'group'])) {
            if (empty($data['data'])) {
                $errors[] = 'single veya group item için data gerekli';
            } elseif (!is_array($data['data'])) {
                $errors[] = 'data array olmalı';
            } else {
                // ESKİ SİSTEM FORMAT: data ARRAY OF OBJECTS olmalı
                // Single: [{"lesson_id": "503", "lecturer_id": "158", ...}]
                // Group:  [{"lesson_id": "503", ...}, {"lesson_id": "504", ...}]

                // En az 1 eleman olmalı
                if (count($data['data']) === 0) {
                    $errors[] = 'data boş olamaz';
                } else {
                    // İlk elemanı kontrol et
                    if (!is_array($data['data'][0])) {
                        $errors[] = 'data[0] array olmalı (eski format: array of objects)';
                    } elseif (!isset($data['data'][0]['lesson_id'])) {
                        $errors[] = 'data[0] içinde lesson_id gerekli';
                    }
                }
            }
        }

        return empty($errors)
            ? ValidationResult::success()
            : ValidationResult::failed($errors);
    }

    /**
     * Batch validasyon - birden fazla item'ı doğrular
     * @param array $itemsData
     * @return ValidationResult
     */
    public function validateBatch(array $itemsData): ValidationResult
    {
        $allErrors = [];

        foreach ($itemsData as $index => $itemData) {
            $result = $this->validate($itemData);
            if (!$result->isValid) {
                foreach ($result->errors as $error) {
                    $allErrors[] = "Item #{$index}: {$error}";
                }
            }
        }

        return empty($allErrors)
            ? ValidationResult::success()
            : ValidationResult::failed($allErrors);
    }
}
