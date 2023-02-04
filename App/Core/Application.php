<?php

namespace App\Core;

class Application
{
    protected $controller = "HomeController";
    protected $action = "IndexAction";
    protected $parameters = array();

    public function __construct()
    {
        $this->ParseURL();
        try {
            $this->controller =  "App\\Controllers\\".$this->controller;
            $this->controller = new $this->controller;
            if (method_exists($this->controller, $this->action)) {
                call_user_func_array([$this->controller, $this->action], $this->parameters);
            } else {
                echo "Böyle Bir Action Yok.";
            }
        } catch (\Throwable $exception) {

            echo "Böyle bir Controller yok yada bir hata oluştu. -> ".$exception->getMessage()." ".$exception->getFile()." ".$exception->getLine();
        }

    }

    /**
     * ParseURL methodu genel mantığı ile şu işlemleri yapar;
     *
     * $_SERVER["REQUEST_URI"] yardımı ile istemci tarafından gönderilen URL yakalanır.
     *
     * trim() fonkisyonu ile URL sonunda bulunursa "/" karakteri temizlenir.
     *
     * explode() fonksiyonu ile URL "/" karakterine göre dizileştirilir.
     *
     * $url değişkeni bir dizi olur. [0] => Controller Adı, [1] => Action Adı, [2} ve Sonrası => Parametreler
     *
     * unset() fonksiyonu ile $url değişkeninde varsa [0] ve [1] indis numaralı elemenlar temizlenir.
     * Geriye kalan değerler parametrelerdir.
     */

    protected function ParseURL()
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