<?php

namespace App\Routers;

use App\Core\Router;

class HomeRouter extends Router
{
    public function IndexAction()
    {
        //$this->callView("home/index");
        $this->Redirect('/admin/');
    }
}