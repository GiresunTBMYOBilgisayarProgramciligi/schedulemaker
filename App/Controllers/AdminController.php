<?php

namespace App\Controllers;

use App\Core\Controller;

class AdminController extends Controller
{
    public function IndexAction()
    {
        //todo if not login redirct to login page
        $this->View("/admin/index");
    }
    public function LoginAction(){
        $this->View("/admin/login");
    }
}