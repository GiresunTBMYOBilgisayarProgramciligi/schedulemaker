<?php

namespace App\Routers;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Core\ImportExportManager;
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
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSetting;
use function App\Helpers\isAuthorized;

/**
 * todo Router görevi sefece gelen isteiği ilgili Controller a yönlendirmek. gerekl işlemleri ve dönülecek view i controller belirler.
 */
class AjaxRouter extends Router
{
    /**
     * @var array Ajax cevap verisi
     */
    public array $response = [];
    /**
     * @var array Ajax isteği verileri
     */
    private array $data = [];
    private array $files = [];

    private User|null $currentUser = null;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!$this->checkAjax()) {
            $_SESSION["errors"][] = "İstek Ajax isteği değil";
            $this->Redirect("/admin");
        }
        $userController = new UserController();
        if (!$userController->isLoggedIn()) {
            throw new Exception("Oturumunuz sona ermiş. Lütfen tekrar giriş yapın", 330);
        } else $userController->getCurrentUser();
    }

    /**
     * Gelen isteğin ajax isteği olup olmadığını kontrol eder
     * @return bool
     */
    public function checkAjax(): bool
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
        ) {
            $this->data = $_POST;
            $this->files = $_FILES;
            return true;
        } else
            return false;
    }

    /*
     * User Ajax Actions
     */
    /**
     * Ajax ile gelen verilerden oluşturduğu User modeli ile yeni kullanıcı ekler
     * @return void
     * @throws Exception
     */
    public function addNewUserAction(): void
    {
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
        (new UserController())->saveNew($userData);
        $this->response = array(
            "msg" => "Kullanıcı başarıyla eklendi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * Ajax ile gelen verilerden oluşturduğu User Modeli ile verileri günceller
     * @return void
     * @throws Exception
     */
    public function updateUserAction(): void
    {
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
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function deleteUserAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception();
        }
        (new UserController())->delete($this->data['id']);

        $this->response = array(
            "msg" => "Kullanıcı başarıyla Silindi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Lessons Ajax Actions
     */
    public function addLessonAction(): void
    {
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
        $lesson = $lessonController->saveNew($new_lesson);
        if (!$lesson) {
            throw new Exception("Kullanıcı eklenemedi");
        } else {
            $this->response = array(
                "msg" => "Ders başarıyla eklendi.",
                "status" => "success",
            );
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function updateLessonAction(): void
    {
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
        /**
         *
         */
        if (isAuthorized("lecturer", true) and $lessonData['lecturer_id'] == $this->currentUser->id) {
            $lessonData = [];
            $lessonData['id'] = $this->data['id'];
            $lessonData['size'] = $this->data['size'];
        }
        $lesson = new Lesson();
        $lesson->fill($lessonData);
        $lesson = $lessonController->updateLesson($lesson);
        $this->response = array(
            "msg" => "Ders başarıyla Güncellendi.",
            "status" => "success",

        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function deleteLessonAction(): void
    {
        $lesson = (new Lesson())->find($this->data['id']);
        $currentUser = (new UserController())->getCurrentUser();
        if (!isAuthorized("submanager", false, $lesson)) {
            throw new Exception("Bu dersi silme yetkiniz yok");
        }
        if ($currentUser->id != $lesson->lecturer_id and isAuthorized("lecturer", true)) {
            throw new Exception("Bu dersi silme yetkiniz yok");
        }
        (new LessonController())->delete($lesson->id);

        $this->response = array(
            "msg" => "Ders Başarıyla Silindi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Classrooms Ajax Actions
     */
    /**
     * @throws Exception
     */
    public function addClassroomAction(): void
    {
        $classroomController = new ClassroomController();
        $classroomController->saveNew($this->data);

        $this->response = array(
            "msg" => "Derslik başarıyla eklendi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function updateClassroomAction(): void
    {
        $classroomController = new ClassroomController();
        $classroomData = $this->data;
        $classroom = new Classroom();
        $classroom->fill($classroomData);
        $classroom = $classroomController->updateClassroom($classroom);
        $this->response = array(
            "msg" => "Derslik başarıyla Güncellendi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function deleteClassroomAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Derslik silme yetkiniz yok");
        }
        (new ClassroomController())->delete($this->data['id']);

        $this->response = array(
            "msg" => "Derslik başarıyla silindi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Departments Ajax Actions
     */
    public function addDepartmentAction(): void
    {
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
            );
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function updateDepartmentAction(): void
    {
        $departmentController = new DepartmentController();
        $departmentData = $this->data;
        $department = new Department();
        $department->fill($departmentData);
        $department = $departmentController->updateDepartment($department);

        $this->response = array(
            "msg" => "Bölüm başarıyla Güncellendi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function deleteDepartmentAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Bölüm silme yetkiniz yok");
        }
        (new DepartmentController())->delete($this->data['id']);

        $this->response = array(
            "msg" => "Bölüm Başarıyla Silindi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Programs Ajax Actions
     */
    public function addProgramAction(): void
    {
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
            );
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function updateProgramAction(): void
    {
        $programController = new ProgramController();
        $programData = $this->data;
        $program = new Program();
        $program->fill($programData);
        $programId = $programController->updateProgram($program);

        $this->response = array(
            "msg" => "Program Başarıyla Güncellendi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function deleteProgramAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Program silme yetkiniz yok");
        }
        (new ProgramController())->delete($this->data['id']);

        $this->response = array(
            "msg" => "Program Başarıyla Silindi.",
            "status" => "success",
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function getProgramsListAction($department_id): void
    {
        $programController = new ProgramController();
        $programs = $programController->getProgramsList($department_id);
        $this->response['status'] = "success";
        $this->response['programs'] = $programs;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * Schedules Ajax Actions
     */

    /**
     * @throws Exception
     */
    public function getScheduleHTMLAction(): void
    {
        $scheduleController = new ScheduleController();
        $schedulesHTML = $scheduleController->getSchedulesHTML($this->data);
        $this->response['status'] = "success";
        $this->response['HTML'] = $schedulesHTML;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function getAvailableClassroomForScheduleAction(): void
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Uygun ders listesini almak için yetkiniz yok");
        }
        $scheduleController = new ScheduleController();
        $classrooms = $scheduleController->availableClassrooms($this->data);
        $this->response['status'] = "success";
        $this->response['classrooms'] = $classrooms;
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
     * @throws Exception
     */
    public function saveScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        $classroomController = new ClassroomController();
        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']);
            $lecturer = $lesson->getLecturer();
            $classroom = $classroomController->getListByFilters(["name" => trim($this->data['classroom_name'])])[0];
            //todo bu kısımda her bir model için yetki kontrolü yapılablir. Şuanda saveNew içerisinde yapılıyor. owner sıralamasına göre yapılıyor.
            if (!isAuthorized("submanager", false, $lesson)) {
                // Dersin sahibi yada bölümbaşkanı değilsen yada müdür yardımcısı ve üstü bir rolün yoksa

            }
            /*
             * Ders çakışmalarını kontrol etmek için kullanılacak olan filtreler
             */
            $crashFilters = [
                //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                "owners" => ["program" => $lesson->program_id, "user" => $lecturer->id, "lesson" => $lesson->id],//sıralama yetki kontrolü için önemli
                // Programın türü lesson yada exam
                "type" => "lesson",
                "time_start" => $this->data['time_start'],
                "day" => "day" . $this->data['day_index'],
                "lesson_hours" => $this->data['lesson_hours'],
                "semester_no" => trim($this->data['semester_no']),
                "semester" => $this->data['semester'],
                "academic_year" => $this->data['academic_year'],
            ];
            /**
             * Uzem Sınıfı değilse çakışma kontrolüne dersliği de ekle
             * Bu aynı zamanda Uzem derslerinin programının uzem sınıfına kaydedilmemesini sağlar. Bu sayede unique hatası da oluşmaz
             */
            if ($classroom->type != 3) {
                $crashFilters['owners']['classroom'] = $classroom->id;
            }
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
        } else {
            throw new Exception("Kaydedilecek ders id numarası yok ");
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function saveSchedulePreferenceAction(): void
    {
        $scheduleController = new ScheduleController();
        $filters = [
            "type" => "lesson",
            "semester" => getSetting("semester"),
            "academic_year" => getSetting("academic_year"),
        ];
        $filters = array_merge($filters, $this->data);
        $currentSemesters = getSemesterNumbers($filters["semester"]);
        /**
         * Her iki dönem için de tercih kaydediliyor.
         */
        foreach ($currentSemesters as $semester_no) {
            $filters['semester_no'] = $semester_no;
            $schedule = new Schedule();
            $schedule->fill($filters);
            $savedId = $scheduleController->saveNew($schedule);
            if ($savedId == 0) {
                throw new Exception("Hoca tercihi kaydedilemedi");
            } else {
                $this->response = array_merge($this->response, array("status" => "success"));
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     * @return void
     * @throws Exception
     */
    public function checkLecturerScheduleAction(): void
    {
        $lessonController = new LessonController();
        $scheduleController = new ScheduleController();
        if (!key_exists("semester", $this->data)) {
            $filters["semester"] = getCurrentSemester();
        }
        if (!key_exists("academic_year", $this->data)) {
            $filters["academic_year"] = getSetting("academic_year");
        }

        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']);
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
                    for ($i = 0; $i < 6; $i++) {//day0-5
                        if (!is_null($lessonSchedule->{"day" . $i})) {
                            if ($lessonSchedule->{"day" . $i} === false or is_array($lessonSchedule->{"day" . $i})) {
                                $unavailableCells[$rowIndex + 1][$i + 1] = true; //ilk satır günler olduğu için +1, ilk sütun saatlar olduğu için+1
                            }
                            if ($lessonSchedule->{"day" . $i} === true) {
                                $preferredCells[$rowIndex + 1][$i + 1] = true; //ilk satır günler olduğu için +1, ilk sütun saatlar olduğu için+1
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * Ders programından veri silmek için gerekli kontrolleri yapar
     * @return void
     * @throws Exception
     */
    public function deleteScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
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
                $lesson = (new Lesson())->find($this->data['lesson_id']);
                $lecturer = $lesson->getLecturer();
                $classroom = (new Classroom())->get()->where(["name" => trim($this->data['classroom_name'])])->first();
                //set Owners
                $owners['program'] = $lesson->program_id;
                $owners['user'] = $lecturer->id;
                $owners['lesson'] = $lesson->id;
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
                        "type" => "lesson",//todo bu da istekle gelmeli sınav programı için ayrı fonksiyonlar yazmadan halledilse iyi olur
                        "time" => $this->data['time'],
                        "semester_no" => $this->data['semester_no'],
                        "semester" => $this->data["semester"],
                        "academic_year" => $this->data['academic_year'],
                    ];
                    $scheduleController->deleteSchedule($filters);
                }
            } else {
                throw new Exception("Owner_type belirtilmediğinde lesson_id ve classroom_name belirtilmelidir");
            }
        } else {
            /**
             * Burada null coalescing operatörü (??) ile eksik dizin hatalarını önlüyoruz ve sonra array_filter ile boş değerleri temizliyoruz.
             */
            $filters = array_filter([
                "owner_type" => $this->data["owner_type"] ?? null,
                "owner_id" => $this->data["owner_id"] ?? null,
                "semester" => $this->data["semester"] ?? null,
                "academic_year" => $this->data["academic_year"] ?? null,
                "semester_no" => $this->data["semester_no"] ?? null,
                "type" => $this->data["type"] ?? null,
                "time" => $this->data["time"] ?? null,
                "day_index" => $this->data["day_index"] ?? null,
            ], function ($value) {
                return $value !== null && $value !== '';
            });
            $scheduleController->deleteSchedule($filters);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /**
     * @throws Exception
     */
    public function exportScheduleAction(): void
    {
        if (!isAuthorized('department_head'))
            throw new Exception("Ders Programı Dışa Aktarma Yetkiniz Yok");
        $filters=$this->data;
        if (!key_exists('type', $filters)) {
            throw new Exception("Dışarı aktarma işlemi için tür seçilmemiş.");
        }
        if (!key_exists('owner_type', $filters)) {
            throw new Exception("Dışarı aktarma işlemi için ders programı sahibi seçilmemiş.");
        }
        $importExportManager= new ImportExportManager();
        $importExportManager->exportSchedule($filters);
    }

    /*
     * Setting Actions
     */

    public function saveSettingsAction(): void
    {
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    /*
     * İmport ve Export
     */
    public function importUsersAction(): void
    {
        $importExportManager = new ImportExportManager($this->files);
        $result = $importExportManager->importUsersFromExcel();
        $this->response['status'] = "success";
        $this->response['msg'] = sprintf("%d kullanıcı oluşturuldu,%d kullanıcı güncellendi. %d hatalı kayıt var", $result['added'], $result['updated'], $result['errorCount']);
        $this->response['errors'] = $result['errors'];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }

    public function importLessonsAction(): void
    {
        $importExportManager = new ImportExportManager($this->files, $this->data);
        $result = $importExportManager->importLessonsFromExcel();
        $this->response['status'] = "success";
        $this->response['msg'] = sprintf("%d Ders oluşturuldu,%d Ders güncellendi. %d hatalı kayıt var", $result['added'], $result['updated'], $result['errorCount']);
        $this->response['errors'] = $result['errors'];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->response);
    }
}