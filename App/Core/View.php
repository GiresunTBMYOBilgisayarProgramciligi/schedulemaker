<?php

namespace App\Core;

class View
{
    protected $view_file;
    protected $view_data;

    public function __construct($view_file, $view_data)
    {
        $this->view_file = $view_file;
        $this->view_data = $view_data;
    }

    public function Render()
    {
        $this->view_file = strtolower($this->view_file);
        if (file_exists($_ENV['VIEWS_PATH'] . $this->view_file) . ".php"){
        extract($this->view_data);
        ob_start();
        ob_get_clean();
        include_once($_ENV['VIEWS_PATH'] . $this->view_file . ".php");
    }else{
        echo $_ENV['VIEWS_PATH'] . $this->view_file . ".php Dosyası bulunamadı";
    }
    }
}