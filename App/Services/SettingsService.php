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
        $this->logger->info('Toplu ayar güncellemesi başlatıldı');

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
}
