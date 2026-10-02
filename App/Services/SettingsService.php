<?php

namespace App\Services;

use App\Models\Setting;
use App\Core\Database;
use Exception;

class SettingsService extends BaseService
{
    /**
     * Toplu ayarları (upsert) veritabanına kaydeder.
     * Tüm işlem bir transaction bloğu içinde yapılır.
     *
     * @param array $settingsData Array of SettingDTO objects
     * @return bool
     * @throws Exception
     */
    public function saveMultipleSettings(array $settingsData): bool
    {
        $this->logger->debug('Toplu ayar güncellemesi başlatıldı');

        try {
            return Database::transaction(function () use ($settingsData) {
                foreach ($settingsData as $dto) {
                    // Veritabanında aynı group ve key'e sahip kayıt var mı kontrol et
                    $existingSetting = (new Setting())->get()->where([
                        'group' => $dto->group,
                        'key' => $dto->key
                    ])->first();

                    if ($existingSetting) {
                        $existingSetting->value = $dto->value;
                        $existingSetting->type = $dto->type;
                        $existingSetting->update();
                    } else {
                        $newSetting = new Setting();
                        $newSetting->fill($dto->toArray());
                        $newSetting->create();
                    }
                }
                
                $this->logger->info('Toplu ayar güncellemesi başarıyla tamamlandı');
                return true;
            });
        } catch (Exception $e) {
            $this->logger->error('Ayarlar kaydedilirken hata oluştu: ' . $e->getMessage());
            throw new Exception("Ayarlar kaydedilirken bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Anahtar ve gruba göre ayar modelini döner.
     *
     * @param string|null $key
     * @param string $group
     * @return Setting|null
     * @throws Exception
     */
    public function getSetting(?string $key = null, string $group = "general"): ?Setting
    {
        if (is_null($key)) {
            throw new Exception("Ayar için anahtar girilmelidir");
        }
        return (new Setting())->get()->where(["key" => $key, "group" => $group])->first();
    }

    /**
     * Tüm ayarları [group][key] = value şeklinde dizi olarak döner.
     *
     * @return array
     * @throws Exception
     */
    public function getAllSettings(): array
    {
        $settingModels = (new Setting())->get()->all();
        $settings = [];
        foreach ($settingModels as $setting) {
            $settings[$setting->group][$setting->key] = match ($setting->type) {
                'integer' => (int) $setting->value,
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'json'    => json_decode($setting->value, true),
                default   => $setting->value
            };
        }
        return $settings;
    }
}
