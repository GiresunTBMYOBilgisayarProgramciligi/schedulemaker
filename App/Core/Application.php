<?php
/**
 * Aplication.php dosyasının açıklaması
 */

namespace App\Core;

use Exception;

/**
 * Uygulamanın temel çalıştırma mantığını içeren sınıf.
 *
 * Bu sınıf, gelen URL'yi parse ederek uygun kontrolcü (controller) ve olayları (action) çalıştırır.
 */
class Application
{
    /**
     * Çalıştırılacak kontrolcüyü (Router) saklar.
     *
     * @var string Kontrolcü sınıfının adı.
     */
    protected $router = "HomeRouter";
    /**
     * Kontrolcü içerisinde çalıştırılacak olayı (Action) saklar.
     *
     * @var string Metod adı.
     */
    protected $action = "IndexAction";
    /**
     * Gelen istekteki parametreleri saklar.
     *
     * @var array İstemciden gelen parametreler.
     */
    protected $parameters = array();

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->ParseURL();
        $this->router = "App\\Routers\\" . $this->router;//namespace
        $this->router = new $this->router;
        if (method_exists($this->router, $this->action)) {
            call_user_func_array([$this->router, $this->action], $this->parameters);
        } else {
            throw new Exception("Böyle Bir Action Yok. | " . $this->action);
        }
    }

    /**
     * Gelen URL'yi parse ederek kontrolcü, action ve parametreleri ayıklar.
     *
     * @return void
     *
     * ### Genel Mantık:
     * - `$_SERVER["REQUEST_URI"]` ile istemci tarafından gönderilen URL yakalanır.
     * - `trim()` ile URL sonunda bulunan `/` karakteri temizlenir.
     * - `explode()` ile URL `/` karakterine göre parçalara ayrılır.
     * - Kontrolcü ve action değerleri diziden alınır, geri kalanlar parametre olarak saklanır.
     */
    protected function ParseURL(): void
    {
        $request = trim($_SERVER["REQUEST_URI"], "/");
        if (!empty($request)) {
            $url = explode("/", $request);
            $this->router = isset($url[0]) ? ucfirst($url[0]) . "Router" : "HomeRouter";
            $this->action = isset($url[1]) ? rtrim($url[1], "?") . "Action" : "IndexAction";
            unset($url[0], $url[1]);
            $this->parameters = !empty($url) ? array_values($url) : array();
        } else {
            $this->router = "HomeRouter";
            $this->action = "IndexAction";
            $this->parameters = array();
        }
    }

}