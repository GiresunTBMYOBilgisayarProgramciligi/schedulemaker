<?php
/**
 * Aplication.php dosyasının açıklaması
 */

namespace App\Core;

/**
 * Uygulamanın temel çalıştırma mantığını içeren sınıf.
 *
 * Bu sınıf, gelen URL'yi parse ederek uygun kontrolcü (controller) ve olayları (action) çalıştırır.
 */
class Application
{
    /**
     * Çalıştırılacak kontrolcüyü (Controller) saklar.
     *
     * @var string Kontrolcü sınıfının adı.
     */
    protected $controller = "HomeController";
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

    public function __construct()
    {
        $this->ParseURL();
        try {
            $this->controller = "App\\Controllers\\" . $this->controller;
            $this->controller = new $this->controller;
            if (method_exists($this->controller, $this->action)) {
                call_user_func_array([$this->controller, $this->action], $this->parameters);
            } else {
                echo "Böyle Bir Action Yok.";
            }
        } catch (\Throwable $exception) {

            echo "Böyle bir Controller yok yada bir hata oluştu. -> " . $exception->getMessage() . " " . $exception->getFile() . " " . $exception->getLine();
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
            $this->controller = isset($url[0]) ? ucfirst($url[0]) . "Controller" : "HomeController";
            $this->action = isset($url[1]) ? $url[1] . "Action" : "IndexAction";
            unset($url[0], $url[1]);
            $this->parameters = !empty($url) ? array_values($url) : array();
        } else {
            $this->controller = "HomeController";
            $this->action = "IndexAction";
            $this->parameters = array();
        }
    }

}