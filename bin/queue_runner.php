<?php

/**
 * ScheduleMaker - CLI Mail Queue Runner
 * 
 * Bu betik sunucu crontab'ı tarafından periyodik olarak çalıştırılarak
 * kuyrukta bekleyen e-postaları parça parça (batch) gönderir.
 * 
 * Kullanım (Crontab):
 * * * * * * php /path/to/schedulemaker/bin/queue_runner.php >> /path/to/schedulemaker/Logs/queue.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Bu betik yalnızca CLI (komut satırı) üzerinden çalıştırılabilir.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\MailQueueService;
use App\Core\Log;

// .env yükle
if (file_exists(dirname(__DIR__) . '/App/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__) . '/App');
    $dotenv->safeLoad();
}

$startTime = microtime(true);
$options = getopt('', ['limit::', 'max-attempts::', 'verbose::']);
$batchSize = isset($options['limit']) ? (int)$options['limit'] : null;
$maxAttempts = isset($options['max-attempts']) ? (int)$options['max-attempts'] : null;
$isVerbose = isset($options['verbose']);

$queueLogger = Log::channel('queue');

try {
    $service = new MailQueueService();
    $effectiveBatch = $batchSize ?? (int)\App\Helpers\getSettingValue('mail_batch_size', 'mail', 10);
    $effectiveAttempts = $maxAttempts ?? (int)\App\Helpers\getSettingValue('mail_max_attempts', 'mail', 3);

    $result = $service->processQueue($batchSize, $maxAttempts);
    $duration = round(microtime(true) - $startTime, 3);

    // Yalnızca işlenen e-posta olduğunda log kaydı oluştur (kuyruk boşken log dosyalarının ve panonun gereksiz dolmasını önler)
    if ($result['processed'] > 0) {
        $message = "Mail kuyruğu tamamlandı ({$duration}s): İşlenen: {$result['processed']}, Başarılı: {$result['sent']}, Başarısız: {$result['failed']}";
        $queueLogger->info($message, [
            'processed' => $result['processed'],
            'sent'      => $result['sent'],
            'failed'    => $result['failed'],
            'duration'  => $duration,
            'batch'     => $effectiveBatch,
            'attempts'  => $effectiveAttempts,
        ]);
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    } elseif ($isVerbose) {
        echo "[" . date('Y-m-d H:i:s') . "] Mail kuyruğu boş ({$duration}s).\n";
    }

    exit(0);
} catch (\Throwable $e) {
    $errorMessage = "CLI Queue Runner hatası: " . $e->getMessage();
    $queueLogger->error($errorMessage, [
        'exception' => $e
    ]);
    echo "[" . date('Y-m-d H:i:s') . "] HATA: {$errorMessage}\n";
    exit(1);
}
