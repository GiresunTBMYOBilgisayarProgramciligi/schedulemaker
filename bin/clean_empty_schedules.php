<?php
/**
 * Geçici Veritabanı Temizleme Scripti
 * 
 * Bu script, içerisinde hiçbir ders/sınav öğesi (schedule_item) bulunmayan 
 * boş `schedules` kayıtlarını tespit edip veritabanından temizler.
 * 
 * Kullanım (CLI):
 * php bin/clean_empty_schedules.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\Schedule\SchedulePublishService;

// Ortam değişkenlerini yükle
if (file_exists(dirname(__DIR__) . '/App/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__) . '/App');
    $dotenv->load();
}

$isCli = (php_sapi_name() === 'cli');

function output(string $message): void {
    global $isCli;
    if ($isCli) {
        echo $message . PHP_EOL;
    } else {
        echo htmlspecialchars($message) . "<br>" . PHP_EOL;
    }
}

try {
    output("=== BOŞ SCHEDULE KAYITLARI TEMİZLEME İŞLEMİ BAŞLATILDI ===");
    
    $publishService = new SchedulePublishService();
    $deletedCount = $publishService->cleanEmptySchedules();

    if ($deletedCount === 0) {
        output("Temizlenecek boş schedule kaydı bulunamadı. Veritabanı zaten temiz.");
    } else {
        output("BAŞARILI: " . $deletedCount . " adet boş schedule kaydı silindi.");
    }
    output("==========================================================");
} catch (Exception $e) {
    output("HATA OLUŞTU: " . $e->getMessage());
}
