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
use App\Models\Schedule;
use App\Models\User;

class AjaxRouter extends Router
{
    /**
     * @var array Ajax cevap verisi
     */
    public array $response = []; //todo bu kullanılırken =array şeklinde değil. push yada [] şeklindekullanılmalı. kontrol edilip düzeltilmeli
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
                $classroom = $classroomController->saveNew($new_classroom);
                if (!$classroom) {
                    throw new \Exception("Derslik oluşturulamadı");
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
                $classroom = $classroomController->updateClassroom($classroom);
                if (!$classroom) {
                    throw new \Exception("Derslik Güncellenemedi");
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
                $this->response = array(
                    "msg" => "Bölüm başarıyla eklendi.",
                    "status" => "success",
                    "redirect" => "/admin/adddepartment",
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

    /*
     * Schedules Ajax Actions
     */
    /**
     * @throws \Exception
     */
    public function getScheduleTableAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $table = $scheduleController->createScheduleTable($this->data);
                $this->response['status'] = "success";
                $this->response['table'] = $table;
            } catch (\Exception $e) {
                $this->response['status'] = 'error';
                $this->response['msg'] = $e->getMessage();
            }
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function getAvailableLessonsForScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $lessons = $scheduleController->availableLessons($this->data);
                $this->response['status'] = "success";
                $this->response['lessons'] = $lessons;
            } catch (\Exception $e) {
                $this->response['status'] = 'error';
                $this->response['msg'] = $e->getMessage();
            }
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function getAvailableClassroomForScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $classrooms = $scheduleController->availableClassrooms($this->data);
                $this->response['status'] = "success";
                $this->response['classrooms'] = $classrooms;
            } catch (\Exception $exception) {
                $this->response['status'] = "error";
                $this->response['msg'] = $exception->getMessage();
            }
        };
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * Program bilgilerini veri tabanına kaydeder. Aşağıdaki bilgileri alır
     * "lesson_id" Programa eklenen dersin id numarası
     * "time_start" programa eklenen dersin başlangıç saati
     * "lesson_hours" programa eklenen dersin kaç saat eklendiği
     * "day_index", programa eklenecek dersin eklendiği günün index numarası
     * "classroom_name"
     * "season"
     *
     * @return void
     */
    public function saveScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $lessonController = new LessonController();
                $scheduleController = new ScheduleController();
                $classroomController = new ClassroomController();
                if (key_exists("lesson_id", $this->data)) {
                    $lesson = $lessonController->getLesson($this->data['lesson_id']);
                    $lecturer = $lesson->getLecturer();
                    $classroom = $classroomController->getListByFilters(["name" => trim($this->data['classroom_name'])])[0];
                    /*
                     * Ders çakışmalarını kontrol etmek için kullanılacak olan filtreler
                     */
                    $crashFilters = [
                        //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                        "owners" => ["user" => $lecturer->id, "classroom" => $classroom->id, "program" => $lesson->program_id, "lesson" => $lesson->id],
                        // Programın türü lesson yada exam
                        "type" => "lesson",
                        "time_start" => $this->data['time_start'],
                        "day" => "day" . $this->data['day_index'],
                        "lesson_hours" => $this->data['lesson_hours'],
                        "season" => trim($this->data['season']),];
                    if ($scheduleController->checkScheduleCrash($crashFilters)) {// çakışma yok ise
                        /**
                         * birden fazla saat eklendiğinde başlangıç saati ve saat bilgisine göre saatleri dizi olarak dindürür
                         *
                         */
                        $timeArray = $scheduleController->generateTimesArrayFromText($this->data['time_start'], $this->data['lesson_hours']);
                        /*
                         * her bir saat için ayrı ekleme yapılacak
                         */
                        foreach ($timeArray as $time) {
                            /**
                             * veri tabanına eklenecek gün verisi
                             */
                            $day = [
                                "lesson_id" => $this->data['lesson_id'],
                                "classroom_id" => $classroom->id,
                                "lecturer_id" => $lecturer->id,
                            ];

                            $schedule = new Schedule();
                            /*
                             * Bir program kaydı yapılırken kullanıcı, sınıf, program ve ders için birer kayıt yapılır.
                             * Bu değerler için döngü oluşturuluyor
                             */
                            foreach ($crashFilters['owners'] as $owner_type => $owner_id) {
                                $schedule->fill([
                                    "type" => "lesson",
                                    "owner_type" => $owner_type,
                                    "owner_id" => $owner_id,
                                    "day" . $this->data['day_index'] => $day,
                                    "time" => $time,
                                    "season" => trim($this->data['season']),
                                ]);
                                $savedId = $scheduleController->saveNew($schedule);
                                if ($savedId == 0) {
                                    $this->response['status'] = "error";
                                    $this->response['msg'] = $owner_type . " kaydı yapılırken hata oluştu";
                                } else
                                    $this->response[$owner_type . "_result"] = $savedId;
                            }
                        }

                        $this->response = array_merge($this->response, array("status" => "success", "msg" => "Bilgiler Kaydedildi"));
                    } else {
                        $this->response = [
                            "msg" => "Programda Çakışma var",
                            "status" => "error"
                        ];
                    }
                } else throw new \Exception("Kaydedilecek ders id numarası yok ");
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
                            "09.00 - 09.50",
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

    public function deleteScheduleAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $lessonController = new LessonController();
                $classroomController = new ClassroomController();
                $lesson = $lessonController->getLesson($this->data['lesson_id']);
                $lecturer = $lesson->getLecturer();
                $classroom = $classroomController->getListByFilters(["name" => trim($this->data['classroom_name'])])[0];
                $owners = ["user" => $lecturer->id, "classroom" => $classroom->id, "program" => $lesson->program_id, "lesson" => $lesson->id];
                $day = [
                    "lesson_id" => $lesson->id,
                    "classroom_id" => $classroom->id,
                    "lecturer_id" => $lecturer->id,
                ];
                foreach ($owners as $owner_type => $owner_id) {
                    $filters = [
                        "owner_type" => $owner_type,
                        "owner_id" => $owner_id,
                        "day_index" => $this->data['day_index'],
                        "day" => $day,
                        "type" => "lesson",
                        "time" => $this->data['time'],
                        "season" => $this->data['season'],
                    ];
                    $scheduleController->deleteSchedule($filters);
                }
            } catch (\Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "status" => "error"
                ];
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }
}