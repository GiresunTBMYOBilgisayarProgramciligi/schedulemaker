<?php

namespace App\Validators;

class SettingsValidator extends BaseValidator
{
    /**
     * Toplu ayar verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler (Tüm settings dizisi)
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            $errors[] = 'Ayarlar verisi geçerli bir formatta değil.';
            return ValidationResult::failed($errors);
        }

        foreach ($data['settings'] as $group => $settings) {
            if (empty($group)) {
                $errors[] = 'Ayar grubu (group) boş olamaz.';
            }

            foreach ($settings as $key => $settingData) {
                if (empty($key)) {
                    $errors[] = "Ayar anahtarı (key) boş olamaz. (Grup: $group)";
                }

                if (!isset($settingData['type']) || empty($settingData['type'])) {
                    $errors[] = "'$key' anahtarı için geçerli bir tip (type) belirtilmemiş. (Grup: $group)";
                }

                // Check allowed types
                $allowedTypes = ['string', 'integer', 'boolean', 'json', 'array'];
                if (isset($settingData['type']) && !in_array($settingData['type'], $allowedTypes)) {
                    $errors[] = "'$key' anahtarı için geçersiz tip: '{$settingData['type']}'. (Grup: $group)";
                }
            }
        }

        if (!empty($errors)) {
            return ValidationResult::failed($errors);
        }

        return ValidationResult::success();
    }
}
