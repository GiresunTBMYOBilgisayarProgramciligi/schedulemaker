<?php

namespace App\Routers;

use App\Controllers\UserController;
use App\Core\Router;
use App\Models\User;

class AjaxRouter extends Router
{
    /**
     * @var array Ajax cevap verisi
     */
    public array $response = [];
    /**
     * @var array Ajax isteği verileri
     */
    private $data = [];

    /**
     * Gelen isteğin ajax isteği olup olmadığını kontrol eder
     * todo checkAjax metodu her Ajax isteği öncesinde çağrıldığı için bir Middleware ya da base sınıfında tanımlanabilir.
     * @return bool
     */
    public function checkAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
        ) {
            $this->data = $_POST;
            return true;
        } else
            return false;
    }

    public function addNewUserAction()
    {
        if ($this->checkAjax()) {
            try {
                $usersController = new UserController();
                $userData = $this->data;
                $new_user = new User();
                $new_user->fill($userData);
                $respons = $usersController->saveNew($new_user);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Kullanıcı başarıyla eklendi.",
                        "status" => "success"
                    );
                }
            } catch (\Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "status" => "error"
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }

    }
    public function updateUserAction()
    {
        if ($this->checkAjax()) {
            try {
                $usersController = new UserController();
                $userData = $this->data;
                $new_user = new User();
                $new_user->fill($userData);
                $respons = $usersController->updateUser($new_user);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Kullanıcı başarıyla Güncellendi.",
                        "status" => "success"
                    );
                }
            } catch (\Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "status" => "error"
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }

    }

    public function loginAction()
    {
        if ($this->checkAjax()) {
            try {
                $usersController = new UserController();
                $usersController->login([
                    'mail' => $this->data['mail'],
                    'password' => $this->data['password'],
                    "remember_me" => isset($this->data['remember_me'])
                ]);

                $this->response = array(
                    "msg" => "Kullanıcı başarıyla Girişyaptı.",
                    "redirect" => "/admin",
                    "status" => "success"
                );
            } catch (\Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "status" => "error"
                ];
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }
}