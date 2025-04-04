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
    protected $view_data;

    /**
     * @param string $view_folder çalıştırılacak view klasörü
     * @param string $view_page çalıştırılacak view dosyası
     * @param array $view_data çalıştırılacak view dosyasında kullanılacak veriler
     */
    public function __construct(string $view_folder, string $view_page, array $view_data)
    {
        $this->view_folder = $view_folder;
        $this->view_page = $view_page;
        $this->view_data = $view_data;
    }

    /**
     * @throws Exception
     */
    public function Render(): void
    {
        try {
            $this->view_folder = $_ENV['VIEWS_PATH'] . '/' . strtolower($this->view_folder);
            if (is_dir($this->view_folder)) {
                /**
                 * view_page dosyası theme.php içerisinde yüklenecek
                 */
                if (file_exists($this->view_folder . '/pages/' . $this->view_page . '.php')) {
                    extract($this->view_data);
                    ob_start();
                    include $this->view_folder . '/' . 'theme.php';
                    ob_end_flush();
                } else {
                    throw new Exception($this->view_folder . '/pages/' . $this->view_page . '.php' . "View dosyası mevcut değil ");
                }
            } else {
                throw new Exception('View folder does not exist');
            }
        } catch (Exception $exception) {
            //todo view içerisindeki hatalar bu şekilde gösteriliyor. ama hatalar sayfanın yüklenmesini engelleyebiliyor
            //todo bu şekilde bir form elemanı içerisinde çalıştırıldığı için hata gösterilmiyor
            error_log($exception->getMessage().$exception->getTraceAsString());
            echo '<script>
                    document.addEventListener("DOMContentLoaded", function () {
                            alert("'. $exception->getMessage() . '");
                    });
                </script>';
        }

    }
}