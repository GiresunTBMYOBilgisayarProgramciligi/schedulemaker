<?php

namespace App\Core;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;

class Router
{
    protected $view_data = [];
    protected AssetManager $assetManager;
    
    public function __construct()
    {
        $this->view_data = [];
        $this->assetManager = new AssetManager();
        $this->view_data["assetManager"] = $this->assetManager; // View'da kullanmak için
    }

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
    public function callView(string $view_path): void
    {
        // Tüm parçaları "/" işaretine göre ayır
        $path_parts = explode("/", $view_path);

        $folder = $path_parts[0] ?? 'admin'; // Default fallback?
        $page = $path_parts[1] ?? 'index';
        $file = $path_parts[2] ?? 'index';

        // View nesnesini oluştur ve render et
        $view = new View($folder, $page, $file);
        $view->Render($this->view_data);
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
    /**
     * İstenen action bulunamadığında çalışacak varsayılan metod.
     * View yapısına uygun bir dosya varsa onu render eder.
     *
     * @param string $action İstenen action adı (örn: settingsAction)
     * @param array $params URL parametreleri
     * @return void
     * @throws Exception Action ve View bulunamazsa hata fırlatır
     */
    public function defaultAction(string $action, array $params = []): void
    {
        $this->logger()->debug("Default action", ["action"=> $action,'params'=>$params]);
        // Router adı: AdminRouter -> admin
        $folder = strtolower(str_replace("Router", "", (new \ReflectionClass($this))->getShortName()));
        
        // Action adı: settingsAction -> settings
        $page = strtolower(str_replace("Action", "", $action));
        
        // Dosya adı: Parametre varsa ilki, yoksa index
        $file = 'index';
        $this->logger()->debug("Default action", ["folder"=> $folder,'page'=>$page,'file'=>$file]);
        if (!empty($params) && isset($params[0])) {
            $fileCandidate = $params[0];
            // Güvenlik veya format kontrolü yapılabilir
            // Dosyanın varlığını View sınıfı kontrol edecek, biz sadece path oluşturuyoruz
            
            // Eğer dosya varsa onu kullanmak isteriz ama burada dosya kontrolü yapmak yerine 
            // View sınıfına bırakmak daha doğru olabilir veya Application'daki mantığı buraya taşıyoruz.
            // Önce parametreli yolu deneyelim:
            
            $viewPath = "$folder/$page/$fileCandidate";
            // Bu dosyanın varlığını kontrol etmek için View'in exception atmasını yakalayabiliriz
            // Ancak Router içinde olduğumuz için callView zaten exception fırlatıyor.
            
            // Burada bir try-catch yapısı kuramayız çünkü callView void dönüyor ve View->Render exception atıyor.
            // Ama parametrenin dosya adı olup olmadığını bilmiyoruz.
            // Kullanıcı logic'i: "example" parametresi varsa "example.php" yi açsın.
            
            // Şöyle bir strateji izleyelim:
            // 1. Parametreyi dosya adı olarak kabul edip render etmeyi dene.
            // 2. Başarısız olursa varsayılan index dosyasını dene.
            $this->logger()->debug("Default action", ["viewPath"=> $viewPath]);
            $this->view_data["page_title"] = $file . "Sayfası";
            $this->view_data = array_merge($this->view_data, $params);
            $this->callView($viewPath);
            return;
        }
    }
}