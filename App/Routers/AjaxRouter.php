<?php
/**
 * todo ajax işlemi sonrası hangi sayfadan gelindiyse o sayfaya yönlendirme yapılabilir.
 * todo Program sayfasından ders düzenleme işlemine girildiğinde geri program sayfasına dönmeli
 */

namespace App\Routers;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;
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
                /*
                 * Eğer bölüm ve program seçilmediyse o alarlar kullanıcı verisinden siliniyor
                 */
                if (is_null($userData['department_id']) or $userData['department_id'] == '0') {
                    unset($userData['department_id']);
                }
                if (is_null($userData['program_id']) or $userData['program_id'] == '0') {
                    unset($userData['program_id']);
                }
                $new_user = new User();
                $new_user->fill($userData);
                $respons = $usersController->saveNew($new_user);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Kullanıcı başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/adduser",
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
                /*
                 * Eğer bölüm ve program seçilmediyse o alarlar null olarak atanıyor
                 */
                if ($userData['department_id'] == '0') {
                    $userData['department_id'] = null;
                }
                if ($userData['program_id'] == '0') {
                    $userData['program_id'] = null;
                }
                $new_user = new User();
                $new_user->fill($userData);
                $respons = $usersController->updateUser($new_user);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Kullanıcı başarıyla Güncellendi.",
                        "status" => "success",
                        "redirect" => "/admin/listusers",
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
                    "msg" => "Kullanıcı başarıyla Giriş yaptı.",
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
                /*
                 * Eğer bölüm ve program seçilmediyse o alarlar kullanıcı verisinden siliniyor
                 */
                if (is_null($lessonData['department_id']) or $lessonData['department_id'] == '0') {
                    unset($lessonData['department_id']);
                }
                if (is_null($lessonData['program_id']) or $lessonData['program_id'] == '0') {
                    unset($lessonData['program_id']);
                }
                $new_lesson = new Lesson();
                $new_lesson->fill($lessonData);
                $respons = $lessonController->saveNew($new_lesson);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Ders başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/addlesson", /* todo form temizlemek yerine sayfa yeniden yükleniyor. Modal footer a bir chackbox eklenerek form temizleme işlemi yapılabilir.*/
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
                /*
                 * Eğer bölüm ve program seçilmediyse o alarlar null olarak atanıyor
                 */
                if ($lessonData['department_id'] == '0') {
                    $lessonData['department_id'] = null;
                }
                if ($lessonData['program_id'] == '0') {
                    $lessonData['program_id'] = null;
                }
                $lesson = new Lesson();
                $lesson->fill($lessonData);
                $respons = $lessonController->updateLesson($lesson);
                if ($respons['status'] == 'error') {
                    throw new \Exception($respons['msg']);
                } else {
                    $this->response = array(
                        "msg" => "Ders başarıyla Güncellendi.",
                        "status" => "success",
                        "redirect" => "/admin/listlessons",
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
                        "status" => "success",
                        "redirect" => "/admin/addclassroom",
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
                        "status" => "success",
                        "redirect" => "/admin/listclassrooms",
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
                    "msg" => "Derslik başarıyla silindi.",
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
                        "status" => "success",
                        "redirect" => "/admin/adddepartment",
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
                        "status" => "success",
                        "redirect" => "/admin/listdepartments",
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
                    "msg" => "Bölüm Başarıyla Silindi.",
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
                        "status" => "success",
                        "redirect" => "/admin/addprogram",
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
                        "msg" => "Program Başarıyla Güncellendi.",
                        "status" => "success",
                        "redirect" => "/admin/listprograms",
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
                    "msg" => "Program Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listprograms",
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }

    public function getProgramsListAction($department_id)
    {
        $programController = new ProgramController();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($programController->getProgramsList($department_id));
    }

    public function getScheduleTableAction()
    {

        if ($this->checkAjax()) {
            $scheduleController = new ScheduleController();
            $table = $scheduleController->createScheduleTable($this->data);
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($table);
    }

    public function getAvailableLessonsForScheduleAction()
    {
        if ($this->checkAjax()) {
            $scheduleController = new ScheduleController();
            $lessons = $scheduleController->availableLessons($this->data);
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($lessons);
    }

    public function getAvailableClassroomForScheduleAction()
    {
        if ($this->checkAjax()) {
            $scheduleController = new ScheduleController();
            $classrooms = $scheduleController->availableClassrooms($this->data);
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($classrooms);
    }

    public function saveScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $lessonController = new LessonController();
                $scheduleController = new ScheduleController();
                if (key_exists("lesson_id", $this->data)) {//todo key bilgilerinin yazımı için bir standart lazım
                    $lesson = $lessonController->getLesson($this->data['lesson_id']);
                    $lecturer = $lesson->getLecturer();
                    //var_dump("saveScheduleAction this->data:",$this->data);
                    $crashFilters = ["owner_type" => "user",
                        "owner_id" => $lecturer->id,
                        "type" => "lesson",
                        "time_start" => $this->data['time_start'],
                        "day" => "day" . $this->data['day'],
                        "lesson_hours" => $this->data['lesson_hours'],];
                    if ($scheduleController->checkScheduleCrash($crashFilters)) {
                        // todo saveSchedule
                        $this->response = array("status" => "success", "msg" => "Bilgiler Kaydedildi");
                    } else {
                        $this->response = [
                            "msg" => "Hoca ders programı boş değil",
                            "status" => "error"
                        ];
                    }
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

    public function checkLecturerScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $lessonController = new LessonController();
                $scheduleController = new ScheduleController();
                if (key_exists("lesson_id", $this->data)) {//todo key bilgilerinin yazımı için bir standart lazım
                    $lesson = $lessonController->getLesson($this->data['lesson_id']);
                    $lecturer = $lesson->getLecturer();
                    //var_dump("saveScheduleAction this->data:",$this->data);
                    $filters = [
                        "owner_type" => "user",
                        "owner_id" => $lecturer->id,
                        "type" => "lesson",
                    ];
                    $lessonSchedules = $scheduleController->getListByFilters($filters);
                    if (count($lessonSchedules) > 0) {
                        $unavailableCells = [];
                        $tableRows = [
                            "08.00 - 08.50",
                            "09.00 - 08.50",
                            "10.00 - 10.50",
                            "11.00 - 11.50",
                            "12.00 - 12.50",
                            "13.00 - 13.50",
                            "14.00 - 14.50",
                            "15.00 - 15.50",
                            "16.00 - 16.50"
                        ];
                        foreach ($lessonSchedules as $lessonSchedule) {
                            $row = array_search($lessonSchedule->time, $tableRows);
                            $cells = [];
                            for ($i = 0; $i < 6; $i++) {
                                if (!is_null($lessonSchedule->{"day" . $i}) or $lessonSchedule->{"day" . $i} === false) {
                                    $cells[$i + 1] = true;//ilk sütun saatler olduğu için +1
                                }
                            }
                            $unavailableCells[$row + 1] = $cells; //ilk satır günler olduğu için +1
                        }

                        $this->response = array("status" => "success", "msg" => "", "unavailableCells" => $unavailableCells);
                    } else {
                        $this->response = [
                            "msg" => "Hocanın tüm saatleri müsait",
                            "status" => "success"
                        ];
                    }
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
}