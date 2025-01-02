<?php

namespace App\Core;

use App\Core\View;

class Controller
{
    protected $view;

    /**
     * Belirtilen view dosyasını render eder
     * @param string $view_path örn admin/index ilk bölüm view adı sonrası view içerisinde dosya yolu(page klasörü içerisinde)
     * @param array $data view'e aktarılan veriler
     * @return void
     */
    public function callView(string $view_path, array $data = []): void
    {
        // Tüm parçaları "/" işaretine göre ayır
        $path_parts = explode("/", $view_path);

        // İlk eleman klasör adıdır
        $view_folder = array_shift($path_parts);

        // Geriye kalanları dosya yolu olarak birleştir
        $view_file = implode("/", $path_parts);

        // View nesnesini oluştur ve render et
        $this->view = new View($view_folder, $view_file, $data);
        $this->view->Render();
    }

    /**
     * path ile belirtilen yola yönlendirme oluşturur.
     * @param $path
     * @return void
     */
    public function Redirect($path)
    {
        header("Location: {$path}");
        exit();
    }
}