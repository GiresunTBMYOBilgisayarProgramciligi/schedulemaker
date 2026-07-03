<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\SettingDTO;

class SettingsValidator extends BaseValidator
{
    /**
     * Toplu ayar verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler (Tüm settings dizisi)
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            $errors['settings'] = 'Ayarlar verisi geçerli bir formatta değil.';
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }

        foreach ($data['settings'] as $group => $settings) {
            if (empty($group)) {
                $errors['settings'] = 'Ayar grubu (group) boş olamaz.';
            }

            foreach ($settings as $key => $settingData) {
                // Hata mesajını ayar HTML "name" formatına göre ayarlayalım
                // HTML input name formatı: name="settings[group][key][value]" (veya benzeri, örn: name="settings[genel][title]")
                $errorKey = "settings[{$group}][{$key}]";

                if (empty($key)) {
                    $errors[$errorKey] = "Ayar anahtarı (key) boş olamaz.";
                }

                if (!isset($settingData['type']) || empty($settingData['type'])) {
                    $errors[$errorKey] = "'$key' anahtarı için geçerli bir tip (type) belirtilmemiş.";
                }

                // Check allowed types
                $allowedTypes = ['string', 'integer', 'boolean', 'json', 'array'];
                if (isset($settingData['type']) && !in_array($settingData['type'], $allowedTypes)) {
                    $errors[$errorKey] = "'$key' anahtarı için geçersiz tip: '{$settingData['type']}'.";
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesneleri dizisi döndürür.
     * @param array $data
     * @return SettingDTO[]
     * @throws ValidationException
     */
    public function getDTO(array $data): array
    {
        $this->validate($data);
        
        $settingsData = [];
        foreach ($data['settings'] as $group => $settings) {
            foreach ($settings as $key => $item) {
                $settingsData[] = SettingDTO::fromArray([
                    'group' => $group,
                    'key' => $key,
                    'value' => $item['value'] ?? null,
                    'type' => $item['type'] ?? 'string'
                ]);
            }
        }
        return $settingsData;
    }
}
