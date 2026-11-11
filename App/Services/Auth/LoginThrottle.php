<?php

namespace App\Services\Auth;

use Exception;

/**
 * Giriş denemelerini IP + e-posta bazında sınırlayan basit, dosya tabanlı
 * kaba kuvvet (brute-force) koruması.
 *
 * Veritabanı şeması değişikliği gerektirmeden çalışır: başarısız denemeler
 * App/storage/login_attempts.json içinde tutulur. Aynı IP + e-posta için
 * belirlenen zaman aralığında izin verilen deneme sayısı aşılırsa, pencere
 * dolana kadar giriş engellenir. Başarılı girişte ilgili sayaç sıfırlanır.
 *
 * Okuma + değiştirme + yazma işlemi, eşzamanlı istekler arasında artışların
 * kaybolmaması (ve bu sayede rate-limit'in atlanamaması) için tek bir dosya
 * tanıtıcısı üzerinde flock(LOCK_EX) ile atomik olarak yapılır.
 */
class LoginThrottle
{
    private const MAX_ATTEMPTS  = 5;
    private const WINDOW_SECONDS = 900; // 15 dakika

    /**
     * Bu IP + e-posta için giriş denemesine izin var mı kontrol eder.
     * İzin yoksa, ne kadar sonra tekrar denenebileceğini belirten mesajla
     * istisna fırlatır.
     *
     * @throws Exception
     */
    public static function ensureNotBlocked(string $mail, string $ip): void
    {
        $key   = self::key($mail, $ip);
        $entry = self::withLock(function (array &$data) use ($key) {
            return $data[$key] ?? null;
        });

        if (!$entry) {
            return;
        }

        $elapsed = time() - $entry['first_attempt_at'];
        if ($elapsed >= self::WINDOW_SECONDS) {
            return; // Pencere dolmuş, serbest
        }

        if ($entry['count'] >= self::MAX_ATTEMPTS) {
            $remaining = self::WINDOW_SECONDS - $elapsed;
            $minutes   = max(1, (int)ceil($remaining / 60));
            throw new Exception("Çok fazla başarısız giriş denemesi. Lütfen {$minutes} dakika sonra tekrar deneyin.");
        }
    }

    /**
     * Başarısız giriş denemesini kaydeder.
     */
    public static function recordFailure(string $mail, string $ip): void
    {
        $key = self::key($mail, $ip);
        self::withLock(function (array &$data) use ($key) {
            $now   = time();
            $entry = $data[$key] ?? null;
            if (!$entry || ($now - $entry['first_attempt_at']) >= self::WINDOW_SECONDS) {
                $data[$key] = ['count' => 1, 'first_attempt_at' => $now];
            } else {
                $entry['count']++;
                $data[$key] = $entry;
            }
            return null;
        });
    }

    /**
     * Başarılı girişte sayaç temizlenir.
     */
    public static function clear(string $mail, string $ip): void
    {
        $key = self::key($mail, $ip);
        self::withLock(function (array &$data) use ($key) {
            unset($data[$key]);
            return null;
        });
    }

    private static function key(string $mail, string $ip): string
    {
        return strtolower($mail) . '|' . $ip;
    }

    private static function storePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/login_attempts.json';
    }

    /**
     * Depolama dosyasını kilitler, süresi geçmiş kayıtları ayıklanmış veriyi
     * $mutator'a verir, $mutator'ın yaptığı değişikliği diske yazar ve
     * $mutator'ın dönüş değerini geri döndürür. Tüm okuma+değiştirme+yazma
     * işlemi aynı kilit altında, atomik olarak gerçekleşir.
     *
     * @param callable(array<string, array{count: int, first_attempt_at: int}> &$data): mixed $mutator
     */
    private static function withLock(callable $mutator): mixed
    {
        $file = self::storePath();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            // Dosya açılamıyorsa (izin/disk sorunu) boş veriyle devam et;
            // bu durumda throttle devre dışı kalır ama uygulama çökmez.
            $data = [];
            return $mutator($data);
        }

        try {
            flock($handle, LOCK_EX);

            $content = stream_get_contents($handle);
            $decoded = $content ? json_decode($content, true) : null;
            $data    = is_array($decoded) ? $decoded : [];

            // Pencere dışında kalan eski kayıtları ayıkla
            $now = time();
            foreach ($data as $k => $entry) {
                if (!is_array($entry) || ($now - ($entry['first_attempt_at'] ?? 0)) >= self::WINDOW_SECONDS) {
                    unset($data[$k]);
                }
            }

            $result = $mutator($data);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($data));
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
