<?php

namespace App\Core;

use App\Core\View;

class Controller
{
    protected $view;

    /**
     * Belirtilen view dosyasını render eder
     * @param string $view_path örn admin/index
     * @param array $data view'e aktarılan veriler
     * @return void
     */
    public function callView(string $view_path, array $data = []): void
    {
        list($view_folder, $view_file) = explode("/", $view_path);
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