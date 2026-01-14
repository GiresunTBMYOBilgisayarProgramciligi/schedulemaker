<?php
/**
 * App/Core/View.php
 * Views klasöründeki sayfaların ne şekilde görüntüleceğinin düzenlendiği dosya
 */

namespace App\Core;

use Exception;

/**
 * Ana View Sınıfı
 * Bir kontrolcü tarafından çağırılır
 */
class View
{
    protected $view_folder;
    protected $view_page;
    protected $view_file;

    /**
     * @param string $view_folder views klasörü altındaki klasör (örn: admin)
     * @param string $view_page folder içindeki pages klasörü altındaki klasör (örn: lessons)
     * @param string $view_file page klasörü altındaki dosya (örn: lesson)
     */
    public function __construct(string $view_folder, string $view_page, string $view_file='index')
    {
        $this->view_folder = $view_folder;
        $this->view_page = $view_page;
        $this->view_file = $view_file;
    }

    /**
     * @param array $data view dosyasında kullanılacak veriler
     * @throws Exception
     */
    public function Render(array $data = []): void
    {
        // View klasör yolu: Views/admin
        $folderPath = $_ENV['VIEWS_PATH'] . '/' . strtolower($this->view_folder);

        if (is_dir($folderPath)) {
            // Dosya yolu: Views/admin/pages/lessons/lesson.php
            $filePath = $folderPath . '/pages/' . $this->view_page . '/' . $this->view_file . '.php';

            if (file_exists($filePath)) {
                extract($data);
                // Theme dosyasının yolu: Views/admin/theme.php
                // Theme içinde kullanılmak üzere pagePath değişkeni gerekebilir veya theme.php logic'i değişmeli
                // Theme.php şu an $this->view_page kullanıyor ama artık yapı farklı.
                // Eski yapı: include "pages/" . $this->view_page . ".php";
                // Yeni yapı: include $filePath; (ama theme.php bunu nasıl bilecek?)
                // Theme.php'yi de güncellememiz gerekecek. Şimdilik burada theme.php'yi çağırıyoruz.
                // $this->view_page ve $this->view_file public veya getter ile erişilebilir olmalı veya 
                // theme.php içinde $this->renderPage() gibi bir metot çağrılabilir.
                // Ancak theme.php genellikle $this context'inde çalışır.

                // Theme.php'nin beklentisi: pages altında tek bir dosya.
                // Yeni yapıda: pages/klasör/dosya.
                // Theme.php'yi güncellemeden önce burada bir trick yapabiliriz veya theme.php'yi de güncelleriz.
                // User isteği: "Render folder içerisindeki theme.php ve theme klasörünü kullanarak sayfayı hazırlasın."

                ob_start();
                include $folderPath . '/' . 'theme.php';
                ob_end_flush();
            } else {
                throw new Exception("View dosyası mevcut değil: " . $filePath);
            }
        } else {
            throw new Exception('View folder does not exist: ' . $folderPath);
        }
    }

    /**
     * Sadece belirtilen partial view dosyasını render eder
     * @param string $folder views klasörü altındaki klasör (örn: admin)
     * @param string $page folder içindeki pages klasörü altındaki klasör (örn: schedules)
     * @param string $file partials klasörü altındaki dosya (örn: available_lessons)
     * @param array $data view dosyasında kullanılacak veriler
     * @return string Render edilen HTML içeriği
     * @throws Exception
     */
    public static function renderPartial(string $folder, string $page, string $file, array $data = []): string
    {
        // Dosya yolu: Views/admin/pages/schedules/partials/available_lessons.php
        $fullPath = $_ENV['VIEWS_PATH'] . '/' . $folder . '/pages/' . $page . '/partials/' . $file . '.php';

        if (file_exists($fullPath)) {
            extract($data);
            ob_start();
            include $fullPath;
            return ob_get_clean();
        } else {
            throw new Exception("Partial view dosyası bulunamadı: " . $fullPath);
        }
    }
}