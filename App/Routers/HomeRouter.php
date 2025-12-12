<?php

namespace App\Routers;

use App\Controllers\UserController;
use App\Core\AssetManager;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Exception;

class HomeRouter extends Router
{
    private User|false $currentUser = false;

    public function __construct()
    {
        parent::__construct();
        $this->beforeAction();
    }

    /**
     * Aksiyonlardan önce çalıştırılan kontrol mekanizması
     * @throws Exception
     */
    private function beforeAction(): void
    {
        $userController = new UserController();
        $this->currentUser = $userController->getCurrentUser();
        $this->view_data['currentUser'] = $this->currentUser;
    }

    /**
     * @throws \Exception
     */
    public function IndexAction()
    {
        $userController = new UserController();
        $this->assetManager->loadPageAssets("homeIndex");
        $this->view_data = array_merge($this->view_data, [
            "departments" => (new Department())->get()->where(['active'=>true])->all(),
            "classrooms" => (new Classroom())->get()->all(),
            "lecturers" => $userController->getListByFilters(['!role'=>'admin']),
            "page_title" => "Anasayfa"]);
        $this->callView("home/index");
        //$this->Redirect('/admin/');
    }
}