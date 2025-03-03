<?php
// app/core/ErrorHandler.php

namespace App\Core;

use Exception;

/**
 * Claude ile oluşturularak üzerinde düzenlemeler yapıldı
 */
class ErrorHandler
{
    /**
     * Hata işleyicisini kaydeder
     * PHP'nin varsayılan hata ve istisna işleyicilerini bu sınıfın metodlarıyla değiştirir
     *
     * @return void
     */
    public function register()
    {
        // Hata ve istisna işleyicilerini kaydet
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * PHP hataları için işleyici metod
     * PHP hatalarını yakalar ve bunları istisnalara dönüştürür
     *
     * @param int $level Hata seviyesi (E_ERROR, E_WARNING, vb.)
     * @param string $message Hata mesajı
     * @param string $file Hatanın oluştuğu dosya
     * @param int $line Hatanın oluştuğu satır numarası
     * @return bool Hatanın işlenip işlenmediği
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file, $line)
    {
        // Eğer hata seviyesi şu anki hata raporlama seviyesine dahilse
        if (error_reporting() & $level) {
            // Hatayı istisna olarak yeniden fırlat (yakalanabilir hale getir)
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        // PHP'nin kendi hata işleyicisinin çalışmaması için true dön
        return true;
    }

    /**
     * İstisnaları işleyecek metod
     * Tüm istisnalar için merkezi işleme noktası
     *
     * @param \Exception $exception Yakalanan istisna
     * @return void
     */
    public function handleException($exception)
    {
        // Hatayı logla
        $this->logException($exception);

        /*// İstisna türüne göre farklı şablonları göster
        if ($exception instanceof \App\Core\Exceptions\DatabaseException) {
            // Veritabanı hatası durumunda özel şablon
            $this->renderErrorView('database_error', $exception, 500);
        } elseif ($exception instanceof \App\Core\Exceptions\NotFoundException) {
            // 404 hatası durumunda özel şablon
            $this->renderErrorView('not_found', $exception, 404);
        } elseif ($exception instanceof \App\Core\Exceptions\ValidationException) {
            // Doğrulama hatası durumunda özel şablon
            $this->renderErrorView('validation_error', $exception, 400);
        } else {
            // Diğer tüm hatalar için genel şablon
            $this->renderErrorView('error', $exception, 500);
        }*/

        header("Location: /admin");
        exit();
    }

    /**
     * Ölümcül hatalar için işleyici metod
     * PHP'nin fatal error gibi normal istisna mekanizması ile yakalanamayan hatalarını işler
     *
     * @return void
     */
    public function handleShutdown()
    {
        // Son hata bilgisini al
        $error = error_get_last();

        // Eğer ölümcül bir hata varsa
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Hatayı istisna olarak ele al (handleException ile işle)
            $this->handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    /**
     * İstisnayı loglama metodu
     * Tüm istisnaları yapılandırılmış formatta loglar
     *
     * @param \Exception $exception Loglanacak istisna
     * @return void
     */
    private function logException($exception)
    {
        // Hata mesajını oluştur
        $message = "Exception: " .  mb_convert_encoding($exception->getMessage(), 'UTF-8', 'auto');
        $message .= " in " . $exception->getFile() . " on line " . $exception->getLine();
        $message .= "\nStack trace: " . $exception->getTraceAsString();

        // PHP'nin varsayılan hata loglama mekanizmasını kullan
        // Bu genellikle error_log dosyasına veya syslog'a yazdırır
        error_log($message);
        $_SESSION['error'] = $exception->getMessage();
        // İsterseniz burada başka loglama mekanizmaları da kullanabilirsiniz
        // Örneğin: veritabanına kaydetme, harici bir servis kullanma vb.
    }

    /**
     * Hata görünümlerini render eden metod
     * İstisna ve durum koduna göre uygun hata sayfasını gösterir
     *
     * @param string $view Gösterilecek görünüm şablonu
     * @param \Exception $exception İşlenen istisna
     * @param int $statusCode HTTP durum kodu
     * @return void
     */
    private function renderErrorView($view, $exception, $statusCode)
    {
        // HTTP durum kodunu ayarla
        http_response_code($statusCode);

        // API isteği mi kontrol et
        if ($this->isAjax()) {
            // API istekleri için JSON formatında hata döndür
            $this->renderJsonError($exception, $statusCode);
            return;
        }

        // Görünüm için hata verilerini hazırla
        $errorData = [
            'message' => $exception->getMessage(),
            'code' => $statusCode
        ];

        // Geliştirme ortamında daha fazla detay göster
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $errorData['file'] = $exception->getFile();
            $errorData['line'] = $exception->getLine();
            $errorData['trace'] = $exception->getTraceAsString();
        }

        // Hata şablonunu include et ve göster
        include_once __DIR__ . "/../views/errors/{$view}.php";
    }

    /**
     * JSON formatında hata yanıtı oluşturan metod
     * API istekleri için hata yanıtlarını JSON formatında döndürür
     *
     * @param \Exception $exception İşlenen istisna
     * @param int $statusCode HTTP durum kodu
     * @return void
     */
    private function renderJsonError($exception, $statusCode)
    {
        // Content-Type başlığını JSON olarak ayarla
        header('Content-Type: application/json');

        // Temel hata yanıtını oluştur
        $response = [
            'status' => 'error',
            'message' => $exception->getMessage(),
            'code' => $statusCode
        ];

        // ValidationException için hata detaylarını ekle
        if ($exception instanceof \App\Core\Exceptions\ValidationException) {
            $response['errors'] = $exception->getErrors();
        }

        // Geliştirme ortamında daha fazla detay göster
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
        }

        // JSON yanıtını çıktıla
        echo json_encode($response);
    }

    /**
     * İsteğin API isteği olup olmadığını kontrol eden metod
     * API isteklerini belirleme mantığını içerir
     *
     * @return bool İstek API isteği mi
     */
    private function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
        ) {
            return true;
        } else
            return false;

        /*        // Aşağıdaki iki koşuldan biri sağlanıyorsa API isteği olarak kabul et:

                // 1. Accept başlığı application/json içeriyorsa
                $isJsonAccept = (
                    isset($_SERVER['HTTP_ACCEPT']) &&
                    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
                );

                // 2. İstek URL'si /api/ ile başlıyorsa
                $isApiUrl = (
                    isset($_SERVER['REQUEST_URI']) &&
                    strpos($_SERVER['REQUEST_URI'], '/api/') === 0
                );

                return $isJsonAccept || $isApiUrl;*/
    }
}