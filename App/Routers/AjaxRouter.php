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
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use Exception;
use function App\Helpers\getCurrentSemester;
use function App\Helpers\getSetting;

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
                $user = $usersController->saveNew($new_user);
                if (!$user) {
                    throw new Exception("Kullanıcı Eklenemedi");
                } else {
                    $this->response = array(
                        "msg" => "Kullanıcı başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/adduser",
                    );
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $userId = $usersController->updateUser($new_user);

                $this->response = array(
                    "msg" => "Kullanıcı başarıyla Güncellendi.",
                    "status" => "success",
                    "redirect" => "/admin/listusers",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
            try {
                $usersController = new UserController();
                $usersController->delete($this->data['id']);

                $this->response = array(
                    "msg" => "Kullanıcı başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listusers",
                );

            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                    "msg" => "Kullanıcı başarıyla Giriş yaptı.",
                    "redirect" => "/admin",
                    "status" => "success"
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $user = $lessonController->saveNew($new_lesson);
                if (!$user) {
                    throw new Exception("Kullanıcı eklenemedi");
                } else {
                    $this->response = array(
                        "msg" => "Ders başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/addlesson", /* todo form temizlemek yerine sayfa yeniden yükleniyor. Modal footer a bir chackbox eklenerek form temizleme işlemi yapılabilir.*/
                    );
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $lesson = $lessonController->updateLesson($lesson);
                $this->response = array(
                    "msg" => "Ders başarıyla Güncellendi.",
                    "status" => "success",
                    "redirect" => "/admin/listlessons",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
            try {
                $lessonController = new LessonController();
                $lessonController->delete($this->data['id']);

                $this->response = array(
                    "msg" => "Ders Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listlessons",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
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
                    throw new Exception("Derslik oluşturulamadı");
                } else {
                    $this->response = array(
                        "msg" => "Derslik başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/addclassroom",
                    );
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $this->response = array(
                    "msg" => "Derslik başarıyla Güncellendi.",
                    "status" => "success",
                    "redirect" => "/admin/listclassrooms",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
            try {
                $classroomController = new ClassroomController();
                $classroomController->delete($this->data['id']);

                $this->response = array(
                    "msg" => "Derslik başarıyla silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listclassrooms",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
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
                $department = $departmentController->saveNew($new_department);
                if (!$department) {
                    throw new Exception("Bölüm Eklenemedi");
                } else {
                    $this->response = array(
                        "msg" => "Bölüm başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/adddepartment",
                    );
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $department = $departmentController->updateDepartment($department);

                $this->response = array(
                    "msg" => "Bölüm başarıyla Güncellendi.",
                    "status" => "success",
                    "redirect" => "/admin/listdepartments",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
            try {
                $departmentController = new DepartmentController();
                $departmentController->delete($this->data['id']);

                $this->response = array(
                    "msg" => "Bölüm Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listdepartments",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
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
                $program = $programController->saveNew($new_program);
                if (!$program) {
                    throw new Exception("Program kaydedilemedi");
                } else {
                    $this->response = array(
                        "msg" => "Program başarıyla eklendi.",
                        "status" => "success",
                        "redirect" => "/admin/addprogram",
                    );
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                $programId = $programController->updateProgram($program);

                $this->response = array(
                    "msg" => "Program Başarıyla Güncellendi.",
                    "status" => "success",
                    "redirect" => "/admin/listprograms",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
            try {
                $programController = new ProgramController();
                $programController->delete($this->data['id']);

                $this->response = array(
                    "msg" => "Program Başarıyla Silindi.",
                    "status" => "success",
                    "redirect" => "/admin/listprograms",
                );
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }

    public function getProgramsListAction($department_id)
    {
        if ($this->checkAjax()) {
            try {
                $programController = new ProgramController();
                $programs = $programController->getProgramsList($department_id);
                $this->response['status'] = "success";
                $this->response['programs'] = $programs;
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
            }

        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Schedules Ajax Actions
     */

    public function getScheduleHTMLAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $schedulesHTML = $scheduleController->getSchedulesHTML($this->data);
                $this->response['status'] = "success";
                $this->response['HTML'] = $schedulesHTML;
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
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
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
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
     * "semester_no"
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
                        "semester_no" => trim($this->data['semester_no']),
                        "semester" => $this->data['semester'],
                        "academic_year" => $this->data['academic_year'],];
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
                                    "semester_no" => trim($this->data['semester_no']),
                                    "semester" => $this->data['semester'],
                                    "academic_year" => $this->data['academic_year'],
                                ]);
                                $savedId = $scheduleController->saveNew($schedule);
                                if ($savedId == 0) {
                                    throw new Exception($owner_type . " kaydı yapılırken hata oluştu");
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
                } else throw new Exception("Kaydedilecek ders id numarası yok ");
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }

    public function saveSchedulePreferenceAction()
    {
        if ($this->checkAjax()) {
            try {
                $scheduleController = new ScheduleController();
                $filters = [
                    "type" => "lesson",
                    "semester" => getSetting("semester"),
                    "academic_year" => getSetting("academic_year"),
                ];
                $filters = array_merge($filters, $this->data);
                $schedule = new Schedule();
                $schedule->fill($filters);
                $savedId = $scheduleController->saveNew($schedule);
                if ($savedId == 0) {
                    throw new Exception("Hoca tercihi kaydedilemedi");
                } else {
                    $this->response = array_merge($this->response, array("status" => "success"));
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                if (!key_exists("semester", $this->data)) {
                    $filters["semester"] = getCurrentSemester();
                }
                if (!key_exists("academic_year", $this->data)) {
                    $filters["academic_year"] = getSetting("academic_year");
                }

                if (key_exists("lesson_id", $this->data)) {
                    $lesson = $lessonController->getLesson($this->data['lesson_id']);
                    $lecturer = $lesson->getLecturer();
                    $filters = [
                        "owner_type" => "user",
                        "owner_id" => $lecturer->id,
                        "type" => "lesson",
                        "semester" => $this->data['semester'],
                        "academic_year" => $this->data['academic_year'],
                    ];
                    $lessonSchedules = $scheduleController->getListByFilters($filters);
                    if (count($lessonSchedules) > 0) {
                        $unavailableCells = [];
                        $preferredCells = [];
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
                            $rowIndex = array_search($lessonSchedule->time, $tableRows);
                            $cells = [];
                            for ($i = 0; $i < 6; $i++) {
                                if (!is_null($lessonSchedule->{"day" . $i})) {
                                    if ($lessonSchedule->{"day" . $i} === false) {
                                        $cells[$i + 1] = true;//ilk sütun saatler olduğu için +1
                                        $unavailableCells[$rowIndex + 1] = $cells; //ilk satır günler olduğu için +1
                                    }
                                    if ($lessonSchedule->{"day" . $i} === true) {
                                        $cells[$i + 1] = true;//ilk sütun saatler olduğu için +1
                                        $preferredCells[$rowIndex + 1] = $cells; //ilk satır günler olduğu için +1
                                    }
                                }
                            }
                        }

                        $this->response = array("status" => "success", "msg" => "", "unavailableCells" => $unavailableCells, "preferredCells" => $preferredCells);
                    } else {
                        $this->response = [
                            "msg" => "Hocanın tüm saatleri müsait",
                            "status" => "success"
                        ];
                    }
                }
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
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
                if (!key_exists("semester", $this->data)) {
                    $this->data["semester"] = getSetting("semester");
                }
                if (!key_exists("academic_year", $this->data)) {
                    $this->data["academic_year"] = getSetting("academic_year");
                }
                if (!key_exists("owner_type", $this->data)) {
                    //owner_type yok ise tüm owner_type'lar için döngü oluşturulacak
                    $owners = [];
                    if (key_exists("lesson_id", $this->data) and key_exists("classroom_name", $this->data)) {
                        $lesson = $lessonController->getLesson($this->data['lesson_id']);
                        $lecturer = $lesson->getLecturer();
                        $owners['user'] = $lecturer->id;
                        $owners['lesson'] = $lesson->id;
                        $owners['program'] = $lesson->program_id;
                        $classroom = $classroomController->getListByFilters(["name" => trim($this->data['classroom_name'])])[0];
                        $owners["classroom"] = $classroom->id;
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
                                "semester_no" => $this->data['semester_no'],
                                "semester" => $this->data["semester"],
                                "academic_year" => $this->data['academic_year'],
                            ];
                            $scheduleController->deleteSchedule($filters);
                        }
                    } else throw new Exception("Owner_type belirtilmediğinde lesson_id ve classroom_name belirtilmelidir");
                } else {
                    $filters = [
                        "owner_type" => $this->data["owner_type"],
                        "owner_id" => $this->data["owner_id"],
                        "semester" => $this->data["semester"],
                        "academic_year" => $this->data["academic_year"],
                        "semester_no" => $this->data["semester_no"],
                        "type" => $this->data["type"],
                        "time" => $this->data["time"],
                        "day_index" => $this->data["day_index"],
                    ];
                    $scheduleController->deleteSchedule($filters);
                }

            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Setting Actions
     */

    public function saveSettingsAction()
    {
        if ($this->checkAjax()) {
            try {
                $settingsController = new SettingsController();
                foreach ($this->data['settings'] as $group => $settings) {
                    $settingData['group'] = $group;
                    foreach ($settings as $key => $data) {
                        $data['key'] = $key;
                        $settingData = array_merge($settingData, $data);
                        $setting = new Setting();
                        $setting->fill($settingData);
                        $settingsController->saveNew($setting);
                    }

                }
                $this->response['status'] = "success";
                $this->response['msg'] = "Ayarlar kaydedildi";
            } catch (Exception $e) {
                $this->response = [
                    "msg" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                    "status" => "error"
                ];
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->response);
        }
    }

}