<?php
/**
 * Geçici Veritabanı Temizleme Scripti
 * 
 * Bu script, içerisinde hiçbir ders/sınav öğesi (schedule_item) bulunmayan 
 * boş `schedules` kayıtlarını tespit edip veritabanından temizler.
 * 
 * Kullanım (CLI):
 * php clean_empty_schedules.php
 */

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Ortam değişkenlerini yükle
$dotenv = Dotenv::createImmutable(__DIR__ . "/App");
$dotenv->load();

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
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    output("=== BOŞ SCHEDULE KAYITLARI TEMİZLEME İŞLEMİ BAŞLATILDI ===");
    output("Bağlantı başarılı: " . $_ENV['DB_NAME']);
    output("----------------------------------------------------------");

    // 1. Boş Schedule sayısını hesapla
    $countStmt = $db->query("
        SELECT COUNT(*) 
        FROM schedules s 
        LEFT JOIN schedule_items si ON s.id = si.schedule_id 
        WHERE si.id IS NULL
    ");
    $totalEmpty = (int)$countStmt->fetchColumn();

    if ($totalEmpty === 0) {
        output("Temizlenecek boş schedule kaydı bulunamadı. Veritabanı zaten temiz.");
        output("==========================================================");
        exit;
    }

    output("Tespit edilen toplam boş Schedule sayısı: " . $totalEmpty);
    output("");
    output("Kategori ve Tür Dağılımı:");

    // Dağılımı listele
    $breakdownStmt = $db->query("
        SELECT s.owner_type, s.type, COUNT(*) as count 
        FROM schedules s 
        LEFT JOIN schedule_items si ON s.id = si.schedule_id 
        WHERE si.id IS NULL 
        GROUP BY s.owner_type, s.type
        ORDER BY s.owner_type, s.type
    ");
    $breakdown = $breakdownStmt->fetchAll();

    foreach ($breakdown as $row) {
        output(sprintf(" - [%s / %s]: %d adet", $row['owner_type'], $row['type'], $row['count']));
    }

    output("----------------------------------------------------------");
    output("Silme işlemi uygulanıyor...");

    // 2. Silme işlemini gerçekleştir
    $deletedCount = $db->exec("
        DELETE s 
        FROM schedules s 
        LEFT JOIN schedule_items si ON s.id = si.schedule_id 
        WHERE si.id IS NULL
    ");

    output("BAŞARILI: " . $deletedCount . " adet boş schedule kaydı silindi.");

    // 3. Kalan schedule sayısı
    $remainingCount = (int)$db->query("SELECT COUNT(*) FROM schedules")->fetchColumn();
    output("Veritabanında kalan aktif Schedule sayısı: " . $remainingCount);
    output("==========================================================");
    output("NOT: İşleminiz bittikten sonra bu dosyayı ('clean_empty_schedules.php') sunucudan silmeyi unutmayınız.");

} catch (Exception $e) {
    output("HATA OLUŞTU: " . $e->getMessage());
}
