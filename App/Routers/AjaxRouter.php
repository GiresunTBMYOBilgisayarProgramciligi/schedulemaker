<?php

namespace App\Routers;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
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

    /*
     * User Ajax Actions
     */
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

    public function deleteUserAction()
    {
        if ($this->checkAjax()) {
            $usersController = new UserController();
            $response = $usersController->delete($this->data['id']);

            if ($response['status'] == 'error') {
                throw new \Exception($response['msg']);
            } else {
                $this->response = array(
                    "msg" => "Kullanıcı başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listusers",
                );
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

    /*
     * Lessons Ajax Actions
     */
    public function addLessonAction()
    {
        if ($this->checkAjax()) {
            try {
                $lessonController = new LessonController();
                $lessonData = $this->data;
                $new_lesson = new Lesson();
                $new_lesson->fill($lessonData);
                $respons = $lessonController->saveNew($new_lesson);
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

    public function updateLessonAction()
    {
        if ($this->checkAjax()) {
            try {
                $lessonController = new LessonController();
                $lessonData = $this->data;
                $lesson = new Lesson();
                $lesson->fill($lessonData);
                $respons = $lessonController->updateLesson($lesson);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Ders başarıyla Güncellendi.",
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

    public function deleteLessonAction()
    {
        if ($this->checkAjax()) {
            $lessonController = new LessonController();
            $response = $lessonController->delete($this->data['id']);

            if ($response['status'] == 'error') {
                throw new \Exception($response['msg']);
            } else {
                $this->response = array(
                    "msg" => "Ders Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listlessons",
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }
    /*
     * Classrooms Ajax Actions
     */
    public function addClassroomAction()
    {
        if ($this->checkAjax()) {
            try {
                $classroomController = new ClassroomController();
                $classroomData = $this->data;
                $new_classroom = new Classroom();
                $new_classroom->fill($classroomData);
                $respons = $classroomController->saveNew($new_classroom);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Derslik başarıyla eklendi.",
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

    public function updateClassroomAction()
    {
        if ($this->checkAjax()) {
            try {
                $classroomController = new ClassroomController();
                $classroomData = $this->data;
                $classroom = new Classroom();
                $classroom->fill($classroomData);
                $respons = $classroomController->updateClassroom($classroom);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Derslik başarıyla Güncellendi.",
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

    public function deleteClassroomAction()
    {
        if ($this->checkAjax()) {
            $classroomController = new ClassroomController();
            $response = $classroomController->delete($this->data['id']);

            if ($response['status'] == 'error') {
                throw new \Exception($response['msg']);
            } else {
                $this->response = array(
                    "msg" => "Kullanıcı başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listclassrooms",
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }

    /*
     * Departments Ajax Actions
     */
    public function addDepartmentAction()
    {
        if ($this->checkAjax()) {
            try {
                $departmentController = new DepartmentController();
                $departmentData = $this->data;
                $new_department = new Department();
                $new_department->fill($departmentData);
                $respons = $departmentController->saveNew($new_department);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Bölüm başarıyla eklendi.",
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

    public function updateDepartmentAction()
    {
        if ($this->checkAjax()) {
            try {
                $departmentController = new DepartmentController();
                $departmentData = $this->data;
                $department = new Department();
                $department->fill($departmentData);
                $respons = $departmentController->updateDepartment($department);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Bölüm başarıyla Güncellendi.",
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

    public function deleteDepartmentAction()
    {
        if ($this->checkAjax()) {
            $departmentController = new DepartmentController();
            $response = $departmentController->delete($this->data['id']);

            if ($response['status'] == 'error') {
                throw new \Exception($response['msg']);
            } else {
                $this->response = array(
                    "msg" => "Ders Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listdepartments",
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }
    /*
     * Programs Ajax Actions
     */
    public function addProgramAction()
    {
        if ($this->checkAjax()) {
            try {
                $programController = new ProgramController();
                $programData = $this->data;
                $new_program = new Program();
                $new_program->fill($programData);
                $respons = $programController->saveNew($new_program);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Program başarıyla eklendi.",
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

    public function updateProgramAction()
    {
        if ($this->checkAjax()) {
            try {
                $programController = new ProgramController();
                $programData = $this->data;
                $program = new Program();
                $program->fill($programData);
                $respons = $programController->updateProgram($program);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Program başarıyla Güncellendi.",
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

    public function deleteProgramAction()
    {
        if ($this->checkAjax()) {
            $programController = new ProgramController();
            $response = $programController->delete($this->data['id']);

            if ($response['status'] == 'error') {
                throw new \Exception($response['msg']);
            } else {
                $this->response = array(
                    "msg" => "Ders Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listprograms",
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }
}