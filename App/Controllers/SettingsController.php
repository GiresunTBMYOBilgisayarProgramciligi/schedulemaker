<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Setting;
use App\Core\Gate;
use App\Validators\SettingsValidator;
use App\Services\SettingsService;
use App\Services\MailQueueService;
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
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");            $settingsData = (new SettingsValidator())->getDTO($requestData);

            (new SettingsService())->saveMultipleSettings($settingsData);

            return [
                "status" => "success",
                "msg" => "Ayarlar kaydedildi"
            ];
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
     * @return array
     * @throws Exception
     */
    public function clearLogs(): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $this->database->exec("TRUNCATE TABLE logs");
        return [
            "status" => "success",
            "msg" => "Loglar başarıyla temizlendi"
        ];
    }

    /**
     * Test / Simülasyon mail log dosyasını temizler
     * @return array
     */
    public function clearMailLogs(): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $logFilePath = dirname(__DIR__, 2) . '/Public/mail_log.html';
        if (file_exists($logFilePath)) {
            @unlink($logFilePath);
        }
        return [
            'status' => 'success',
            'msg'    => 'Mail logları başarıyla temizlendi.'
        ];
    }

    /**
     * Mail kuyruğunu manuel olarak tetikler
     * @param array $requestData
     * @return array
     */
    public function processMailQueue(array $requestData = []): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $limit = (isset($requestData['limit']) && (int)$requestData['limit'] > 0) ? (int)$requestData['limit'] : null;
        $service = new MailQueueService();
        $result = $service->processQueue($limit);
        $stats = $service->getQueueStats();

        return [
            'status' => 'success',
            'msg'    => "Kuyruk işlendi: {$result['sent']} başarılı, {$result['failed']} başarısız.",
            'result' => $result,
            'stats'  => $stats
        ];
    }

    /**
     * Başarısız olmuş kuyruk e-postalarını yeniden bekleyen durumuna alır
     * @return array
     */
    public function retryFailedMailQueue(): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $service = new MailQueueService();
        $count = $service->retryFailed();
        $stats = $service->getQueueStats();

        return [
            'status' => 'success',
            'msg'    => "{$count} adet başarısız e-posta yeniden bekleyen kuyruğuna alındı.",
            'count'  => $count,
            'stats'  => $stats
        ];
    }

    /**
     * Gönderilmiş kuyruk kayıtlarını temizler
     * @return array
     */
    public function clearSentMailQueue(): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $service = new MailQueueService();
        $count = $service->clearSentLogs();
        $stats = $service->getQueueStats();

        return [
            'status' => 'success',
            'msg'    => "{$count} adet başarıyla gönderilmiş kayıt temizlendi.",
            'count'  => $count,
            'stats'  => $stats
        ];
    }

    /**
     * Tek bir kuyruk kaydını siler
     * @param array $requestData
     * @return array
     */
    public function deleteMailQueueItem(array $requestData): array
    {
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
        $id = (int)($requestData['id'] ?? 0);
        $service = new MailQueueService();
        $deleted = $service->deleteItem($id);
        $stats = $service->getQueueStats();

        return [
            'status' => $deleted ? 'success' : 'error',
            'msg'    => $deleted ? 'Kuyruk kaydı silindi.' : 'Kayıt bulunamadı.',
            'stats'  => $stats
        ];
    }
}