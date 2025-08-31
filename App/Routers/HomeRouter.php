<?php

namespace App\Routers;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\UserController;
use App\Core\AssetManager;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Exception;

class HomeRouter extends Router
{
    private $view_data = [];
    private User|false $currentUser = false;
    private AssetManager $assetManager;

    public function __construct()
    {
        $this->beforeAction();
        $this->assetManager = new AssetManager();
        $this->view_data["userController"] = new UserController();
        $this->view_data["assetManager"] = $this->assetManager; // View'da kullanmak için
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
        $this->view_data = array_merge($this->view_data, [
            "departments" => (new Department())->get()->all(),
            "classrooms" => (new Classroom())->get()->all(),
            "lecturers" => $userController->getListByFilters(),
            "page_title" => "Anasayfa"]);
        $this->callView("home/index", $this->view_data);
        //$this->Redirect('/admin/');
    }
}