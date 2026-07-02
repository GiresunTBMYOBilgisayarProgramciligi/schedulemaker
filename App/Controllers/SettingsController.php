<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Setting;
use App\Core\Gate;
use App\DTOs\SettingDTO;
use App\Validators\SettingsValidator;
use App\Services\SettingsService;
use Exception;

class SettingsController extends Controller
{
    protected string $table_name = "settings";
    protected string $modelName = "App\Models\Setting";

    /**
     * @param $key
     * @param string $group
     * @return Setting|string
     * @throws Exception
     */
    public function getSetting($key = null, string $group = "general"): Setting|null
    {
        if (is_null($key)) {
            throw new Exception("Ayar için anahtar girilmelidir");
        }
        $settingModel = new Setting();
        return $settingModel->get()->where(["key" => $key, "group" => $group])->first();
    }

    /**
     * Toplu ayarları kaydeder (POST /ajax/settings/save rotası için)
     */
    public function store(array $requestData): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");

        try {
            $settingsData = (new SettingsValidator())->getDTO($requestData);

            (new SettingsService())->saveMultipleSettings($settingsData);

            return [
                "status" => "success",
                "msg" => "Ayarlar kaydedildi"
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Tüm ayarları [group][key]= value şeklinde dizi oarak döndürür
     * @return array
     * @throws Exception
     */
    public function getSettings(): array
    {
        $settingModel = new Setting();
        $settingModels = $settingModel->get()->all();
        $settings = [];
        foreach ($settingModels as $setting) {
            $settings[$setting->group][$setting->key] = match ($setting->type) {
                'integer' => (int) $setting->value,
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'json' => json_decode($setting->value, true),
                default => $setting->value
            };
        }
        return $settings;
    }

    /**
     * Log tablosunu temizler
     * @return void
     * @throws Exception
     */
    public function clearLogs(): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        try {
            $this->database->exec("TRUNCATE TABLE logs");
            return [
                "status" => "success",
                "msg" => "Loglar başarıyla temizlendi"
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => "Loglar temizlenirken bir hata oluştu: " . $e->getMessage()
            ];
        }
    }
}