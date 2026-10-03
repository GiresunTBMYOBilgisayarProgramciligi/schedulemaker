<?php

namespace App\Services;

use stdClass;

/**
 * Dosya tabanlı log okuma, filtreleme ve yönetim servisi.
 * Veritabanı bağımlılığı olmadan JSON formatlı Monolog log dosyalarını doğrudan okur.
 */
class LogReaderService extends BaseService
{
    private string $logDir;

    public function __construct(?string $logDir = null)
    {
        parent::__construct();
        $this->logDir = $logDir ?? ($_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/Logs');
    }

    /**
     * Sistemde mevcut veya yapılandırılmış log kanallarını listeler.
     *
     * @return string[]
     */
    public function getAvailableChannels(): array
    {
        $defaultChannels = ['app', 'auth', 'database', 'mail', 'queue', 'schedule', 'security', 'system'];
        $foundChannels = [];

        if (is_dir($this->logDir)) {
            $files = glob($this->logDir . '/*.log') ?: [];
            foreach ($files as $file) {
                $base = basename($file, '.log');
                // Format: channel-YYYY-MM-DD or channel
                $parts = explode('-', $base);
                if (!empty($parts[0])) {
                    $foundChannels[] = strtolower($parts[0]);
                }
            }
        }

        $all = array_unique(array_merge($defaultChannels, $foundChannels));
        sort($all);
        return array_values($all);
    }

    /**
     * Belirtilen filtrelere göre log kayıtlarını dosyalardan okur (en yeniden eskiye).
     *
     * @param array{channel?: string, level?: string, date?: string, search?: string} $filters
     * @param int $limit
     * @return stdClass[]
     */
    public function getLogs(array $filters = [], int $limit = 500): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }

        $channelFilter = strtolower(trim($filters['channel'] ?? 'all'));
        $levelFilter = strtoupper(trim($filters['level'] ?? ''));
        $dateFilter = trim($filters['date'] ?? '');
        $searchFilter = mb_strtolower(trim($filters['search'] ?? ''), 'UTF-8');

        // Uygun dosyaları bul
        $pattern = $this->logDir . '/*.log';
        if ($channelFilter !== '' && $channelFilter !== 'all') {
            $pattern = $this->logDir . '/' . $channelFilter . '*.log';
        }

        $files = glob($pattern) ?: [];
        if (empty($files)) {
            return [];
        }

        // Dosyaları en son değiştirilme tarihine göre sırala (en yeni dosya en başta)
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $results = [];
        $logIndex = 1;

        foreach ($files as $filePath) {
            if (!is_readable($filePath) || filesize($filePath) === 0) {
                continue;
            }

            // Tarih filtresi varsa dosya adını kontrol et
            if ($dateFilter !== '' && !str_contains(basename($filePath), $dateFilter)) {
                continue;
            }

            // Dosyadaki satırları tersten oku (en son yazılan en başta)
            $lines = $this->readLinesReversed($filePath);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);
                if (!is_array($record)) {
                    continue;
                }

                // Seviye filtresi
                $recordLevel = strtoupper((string)($record['level_name'] ?? $record['level'] ?? ''));
                if ($levelFilter !== '' && $levelFilter !== 'ALL' && $recordLevel !== $levelFilter) {
                    continue;
                }

                // Kanal filtresi (dosya adı dışında record içindeki channel kontrolü)
                $recordChannel = strtolower((string)($record['channel'] ?? 'app'));
                if ($channelFilter !== '' && $channelFilter !== 'all' && $recordChannel !== $channelFilter) {
                    continue;
                }

                // Arama filtresi (mesaj, kullanıcı, IP, URL)
                if ($searchFilter !== '') {
                    $context = $record['context'] ?? [];
                    $haystack = mb_strtolower(
                        ($record['message'] ?? '') . ' ' .
                        ($context['username'] ?? '') . ' ' .
                        ($context['ip'] ?? '') . ' ' .
                        ($context['url'] ?? '') . ' ' .
                        ($context['class'] ?? '') . ' ' .
                        ($context['method'] ?? ''),
                        'UTF-8'
                    );

                    if (!str_contains($haystack, $searchFilter)) {
                        continue;
                    }
                }

                $results[] = $this->mapRecordToObject($record, $logIndex++);

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * En son eklenen logları getirir.
     *
     * @param int $limit
     * @return stdClass[]
     */
    public function getRecent(int $limit = 10): array
    {
        return $this->getLogs([], $limit);
    }

    /**
     * Log dosyalarını temizler.
     *
     * @param string|null $channel Belirli bir kanal veya null (tümü)
     * @return int Temizlenen/silinen dosya sayısı
     */
    public function clearLogs(?string $channel = null): int
    {
        if (!is_dir($this->logDir)) {
            return 0;
        }

        $channel = strtolower(trim((string)$channel));
        if ($channel === '' || $channel === 'all') {
            $files = glob($this->logDir . '/*.log') ?: [];
        } else {
            $files = glob($this->logDir . '/' . $channel . '*.log') ?: [];
        }

        $cleared = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                // Dosyayı sil veya içeriğini sıfırla
                if (@unlink($file) || @file_put_contents($file, '') !== false) {
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    /**
     * Belirtilen saklama süresinden (gün) daha eski log dosyalarını siler.
     *
     * @param int|null $retentionDays Saklama süresi (gün). Null ise ayarlardan okunur.
     * @return int Silinen dosya sayısı
     */
    public function cleanExpiredLogs(?int $retentionDays = null): int
    {
        if ($retentionDays === null) {
            $retentionDays = 14;
            try {
                $db = \App\Core\Database::getConnection();
                $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `group` = 'log' AND `key` = 'log_retention_days'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val !== false && is_numeric($val) && (int)$val > 0) {
                    $retentionDays = (int)$val;
                }
            } catch (\Throwable) {
                // Varsayılan 14 gün
            }
        }

        return \App\Core\Log::cleanExpiredLogs($this->logDir, $retentionDays);
    }

    /**
     * Bir dosyayı bellek tüketimini kontrol altında tutarak sondan başa (ters) satır satır okur.
     *
     * @param string $filePath
     * @return \Generator<string>
     */
    private function readLinesReversed(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return;
        }

        $lineBuffer = '';
        $chunkSize = 8192;
        $fileSize = filesize($filePath);
        $pos = $fileSize;

        while ($pos > 0) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize);

            $combined = $chunk . $lineBuffer;
            $lines = explode("\n", $combined);

            // İlk parça sonraki chunk ile birleşebilir
            $lineBuffer = array_shift($lines) ?? '';

            // Geriye kalan tamamlanmış satırları sondan başa yield et
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if (trim($lines[$i]) !== '') {
                    yield $lines[$i];
                }
            }
        }

        if (trim($lineBuffer) !== '') {
            yield $lineBuffer;
        }

        fclose($handle);
    }

    /**
     * Ham Monolog JSON kaydını View ve Helper'ın beklediği stdClass nesnesine dönüştürür.
     *
     * @param array $record
     * @param int $id
     * @return stdClass
     */
    private function mapRecordToObject(array $record, int $id): stdClass
    {
        $context = $record['context'] ?? [];
        $extra = $record['extra'] ?? [];

        $datetime = $record['datetime'] ?? '';
        if (is_array($datetime) && isset($datetime['date'])) {
            $datetime = $datetime['date'];
        }
        $formattedDate = '';
        if (!empty($datetime)) {
            try {
                $dt = new \DateTime((string)$datetime);
                $formattedDate = $dt->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $formattedDate = (string)$datetime;
            }
        }

        $log = new stdClass();
        $log->id = $id;
        $log->created_at = $formattedDate;
        $log->channel = strtolower((string)($record['channel'] ?? 'app'));
        $log->level = strtoupper((string)($record['level_name'] ?? $record['level'] ?? 'INFO'));
        $log->message = (string)($record['message'] ?? '');

        // Context içindeki standart alanlar
        $log->username = $context['username'] ?? null;
        $log->user_id = $context['user_id'] ?? null;
        $log->class = $context['class'] ?? null;
        $log->method = $context['method'] ?? null;
        $log->function = $context['function'] ?? $context['method'] ?? null;
        $log->file = $context['file'] ?? null;
        $log->line = $context['line'] ?? null;
        $log->url = $context['url'] ?? null;
        $log->ip = $context['ip'] ?? null;
        $log->trace = $context['trace'] ?? null;

        // Context modalı için kalan ekstra veriler
        $cleanContext = $context;
        unset($cleanContext['username'], $cleanContext['user_id'], $cleanContext['class'],
              $cleanContext['method'], $cleanContext['function'], $cleanContext['file'],
              $cleanContext['line'], $cleanContext['url'], $cleanContext['ip'], $cleanContext['trace']);

        if (!empty($extra)) {
            $cleanContext['_extra'] = $extra;
        }

        $log->context = !empty($cleanContext) ? json_encode($cleanContext, JSON_UNESCAPED_UNICODE) : null;

        return $log;
    }
}
