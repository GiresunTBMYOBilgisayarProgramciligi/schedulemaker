<?php

namespace App\Routers;

use App\Repositories\UserRepository;
use App\Middlewares\AuthMiddleware;
use App\Core\AssetManager;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Unit;
use App\Models\User;
use Exception;

/**
 * HomeRouter Sınıfı
 * Sistemin genel (public) anasayfasını yönetir.
 * 
 * Not: Projenin genelinde uygulanan "Single Responsibility Principle" (Tek Sorumluluk Prensibi) 
 * gereği business logic'lerin (iş kurallarının) Controller sınıflarına taşınması mimarisine rağmen, 
 * bu sınıf sadece tek bir eylem (IndexAction) barındırdığından ve karmaşık bir iş kuralı içermediğinden 
 * şimdilik olduğu gibi bırakılmıştır. Gelecekte anasayfa işlemleri karmaşıklaşırsa HomePageController 
 * gibi bir yapıya taşınabilir.
 */
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
            "units" => (new Unit())->get()->where(['active'=>true])->all(),
            "departments" => (new Department())->get()->where(['active'=>true])->all(),
            "classrooms" => (new Classroom())->get()->all(),
            "lecturers" => $userRepository->findBy(['!role'=>'admin']),
            "selected_unit_id" => $_GET['unit_id'] ?? $_GET['unit'] ?? '',
            "selected_department_id" => $_GET['department_id'] ?? $_GET['department'] ?? '',
            "selected_program_id" => $_GET['program_id'] ?? $_GET['program'] ?? '',
            "selected_semester_no" => $_GET['semester_no'] ?? $_GET['semesterNo'] ?? '',
            "page_title" => "Anasayfa"]);
        $this->callView("home/index");
    }
}