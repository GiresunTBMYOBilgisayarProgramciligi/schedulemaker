<?php

namespace App\Core;

/**
 * İmzalama/HMAC işlemleri (örn. "beni hatırla" çerezi) için kullanılan uygulama
 * gizli anahtarını sağlar.
 *
 * .env içinde APP_KEY tanımlıysa onu kullanır. Tanımlı değilse, kaynak koda
 * gömülü ortak bir değere düşmek yerine (bu tüm kurulumlarda aynı secret'ın
 * kullanılması ve kaynak kodunu görebilen herkesin sahte imza üretebilmesi
 * anlamına gelirdi) kuruluma özel rastgele bir anahtar üretip App/storage/
 * altında kalıcı olarak saklar.
 */
class AppSecret
{
    private static ?string $cached = null;

    public static function get(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $envKey = $_ENV['APP_KEY'] ?? '';
        if (is_string($envKey) && $envKey !== '') {
            return self::$cached = $envKey;
        }

        return self::$cached = self::loadOrGenerateFileKey();
    }

    /**
     * Dosyadan anahtarı okur, yoksa üretir ve kalıcı olarak yazar.
     *
     * Okuma + (yoksa) üretme + yazma işlemi, eşzamanlı ilk isteklerin farklı
     * anahtarlar üretip birbirinin üzerine yazmaması (ve bu anahtarlardan
     * biriyle imzalanmış bir çerezin sonradan doğrulanamaz hale gelmemesi)
     * için tek bir dosya tanıtıcısı üzerinde flock(LOCK_EX) ile atomik
     * olarak yapılır.
     */
    private static function loadOrGenerateFileKey(): string
    {
        $dir  = dirname(__DIR__) . '/storage';
        $file = $dir . '/app_key.key';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            self::logPersistenceFailure(
                'APP_KEY dosyası açılamadı (izin veya disk sorunu olabilir); bu istek için geçici bir anahtar üretildi, kalıcı olmayacak.',
                $file
            );
            return bin2hex(random_bytes(32));
        }

        try {
            flock($handle, LOCK_EX);

            $existing = trim((string)stream_get_contents($handle));
            if ($existing !== '') {
                return $existing;
            }

            $key = bin2hex(random_bytes(32));

            rewind($handle);
            ftruncate($handle, 0);
            $written = fwrite($handle, $key);
            fflush($handle);

            if ($written === false || $written < strlen($key)) {
                self::logPersistenceFailure(
                    'APP_KEY dosyaya yazılamadı; bu istek için üretilen anahtar kalıcı olmayacak, sonraki isteklerde farklı bir anahtar üretilebilir.',
                    $file
                );
            } else {
                @chmod($file, 0600);
            }

            return $key;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function logPersistenceFailure(string $message, string $file): void
    {
        try {
            Log::channel('system')->error($message, ['path' => $file]);
        } catch (\Throwable) {
            // Loglama da başarısız olursa sessizce yut; secret üretimini engellemesin
        }
    }
}
