<?php

namespace App\Routers;

use App\Repositories\UserRepository;
use App\Middlewares\AuthMiddleware;
use App\Core\AssetManager;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Exception;

class HomeRouter extends Router
{
    private ?User $currentUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->currentUser = AuthMiddleware::user();
        $this->view_data['currentUser'] = $this->currentUser;
    }



    /**
     * @throws \Exception
     */
    public function IndexAction()
    {
        $userRepository = new UserRepository();
        $this->assetManager->loadPageAssets("homeIndex");
        $this->view_data = array_merge($this->view_data, [
            "departments" => (new Department())->get()->where(['active'=>true])->all(),
            "classrooms" => (new Classroom())->get()->all(),
            "lecturers" => $userRepository->findBy(['!role'=>'admin']),
            "page_title" => "Anasayfa"]);
        $this->callView("home/index");
        //$this->Redirect('/admin/');
    }
}