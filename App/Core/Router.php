<?php

namespace App\Core;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;

class Router
{
    protected $view;
    /**
     * Shared application logger for all controllers.
     */
    protected function logger(): Logger
    {
        return Log::logger();
    }

    /**
     * Standard logging context used across controllers.
     * Adds current user, caller method, URL and IP.
     */
    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    /**
     * Belirtilen view dosyasını render eder
     * @param string $view_path örn admin/index ilk bölüm view adı sonrası view içerisinde dosya yolu(page klasörü içerisinde)
     * @param array $data view'e aktarılan veriler
     * @return void
     * @throws Exception
     */
    /**
     * Belirtilen view dosyasını render eder
     * @param string $view_path örn: admin/lessons/lesson (folder/page/file)
     * @param array $data view'e aktarılan veriler
     * @return void
     * @throws Exception
     */
    public function callView(string $view_path, array $data = []): void
    {
        // Tüm parçaları "/" işaretine göre ayır
        $path_parts = explode("/", $view_path);

        $folder = $path_parts[0] ?? 'admin'; // Default fallback?
        $page = $path_parts[1] ?? 'index';
        $file = $path_parts[2] ?? 'index';

        // View nesnesini oluştur ve render et
        $this->view = new View($folder, $page, $file);
        $this->view->Render($data);
    }

    /**
     * goback değeri false yapılmadığı sürece geri yönlendirme yapar. Reri yönlendirme yoksa belirtilen adrese yönlendirme yapar yol belirtilmezse /admin sayfasına yönlendirir.
     * path ile belirtilen yola yönlendirme oluşturur.
     * @param string|null $path
     * @param bool $goBack true
     * @return void
     */
    #[NoReturn] public function Redirect($path = null, bool $goBack = true): void
    {
        $path = is_null($path) ? "/admin" : $path;
        if ($goBack) {
            $redirect_url = $_SERVER['HTTP_REFERER'] ?? $path;
            header("location: " . $redirect_url);
            exit;
        } else {
            header("Location: {$path}");
            exit();
        }
    }
}