<?php

namespace App\Core;

use App\Core\View;

class Controller
{
    protected $view;

    public function View($view_name, $data = []){
        $this->view = new View($view_name, $data);
        return $this->view->Render();
    }

    public function Redirect($path)
    {
        header("Location: {$path}");
    }
}