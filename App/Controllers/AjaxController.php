<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Users;

class AjaxController extends Controller
{
    public function addNewUserAction()
    {
        sleep(2);
        $response = array(
            "msg" => "Eklendi",
            "status" => "success"
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    }
}