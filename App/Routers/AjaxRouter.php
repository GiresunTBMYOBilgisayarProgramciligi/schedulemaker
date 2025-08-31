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
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;
use function App\Helpers\isAuthorized;

/**
 * todo Router görevi sedece gelen isteiği ilgili Controller a yönlendirmek. gerekl işlemleri ve dönülecek view i controller belirler.
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

    private function sendResponse(): void
    {
        // Cache kontrolü - tarayıcı önbelleğe almasın
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() - 3600) . " GMT"); // 1 saat öncesine ayarlanmış tarih

        // Firefox için bağlantı yönetimi
        header("Connection: keep-alive");

        // İçerik tipi ve karakter kodlaması
        header('Content-Type: application/json; charset=utf-8');
        // CORS başlıkları - ihtiyacınıza göre düzenleyin
        header("Access-Control-Allow-Origin: *"); // Belirli bir domain için sınırlandırabilirsiniz
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
        // Hata raporlamasını kapatın (üretim ortamında)
        ini_set('display_errors', 0);

        // Yanıtın sıkıştırılması (isteğe bağlı, performans artırır)
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'On');
        }

        // JSON çıktısını oluştururken Türkçe karakterler için Unicode desteği
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit; // İşlem sonlandırma
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
        if (!isAuthorized("submanager")) {
            throw new Exception("Kullanıcı oluşturma yetkiniz yok");
        }
        (new UserController())->saveNew($userData);
        $this->response = array(
            "msg" => "Kullanıcı başarıyla eklendi.",
            "status" => "success",
        );
        $this->sendResponse();
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
        if (isset($userData['department_id']) and $userData['department_id'] == '0') {
            $userData['department_id'] = null;
        }
        if (isset($userData['program_id']) and $userData['program_id'] == '0') {
            $userData['program_id'] = null;
        }
        $new_user = new User();
        $new_user->fill($userData);
        if (!isAuthorized("submanager", false, $new_user)) {
            throw new Exception("Kullanıcı bilgilerini güncelleme yetkiniz yok");
        }
        $userId = $usersController->updateUser($new_user);

        $this->response = array(
            "msg" => "Kullanıcı başarıyla Güncellendi.",
            "status" => "success",
        );
        $this->sendResponse();
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
        $this->sendResponse();
    }

    /*
     * Lessons Ajax Actions
     */
    /**
     * @throws Exception
     */
    public function addLessonAction(): void
    {
        $lessonController = new LessonController();
        $lessonData = $this->data;
        /*
         * Eğer bölüm ve program seçilmediyse o alarlar siliniyor
         */
        if (empty($lessonData['lecturer_id'])) {
            throw new Exception("Hoca Seçmelisiniz");
        }
        if (empty($lessonData['department_id'])) {
            throw new Exception("Bölüm Seçmelisiniz");
        }
        if (empty($lessonData['program_id'])) {
            throw new Exception("Program Seçmelisiniz");
        }
        $new_lesson = new Lesson();
        $new_lesson->fill($lessonData);
        if (!isAuthorized("submanager", false, $new_lesson)) {
            throw new Exception("Yeni Ders oluşturma yetkiniz yok");
        }

        $lesson = $lessonController->saveNew($new_lesson);
        if (!$lesson) {
            throw new Exception("Ders eklenemedi");
        } else {
            $this->response = array(
                "msg" => "Ders başarıyla eklendi.",
                "status" => "success",
            );
        }
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
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
         * Hoca ve altı yetkive dersi veren kullanıcı ise
         */
        if (isAuthorized("lecturer", true) and $lessonData['lecturer_id'] == $this->currentUser->id) {
            $lessonData = [];
            $lessonData['id'] = $this->data['id'];
            $lessonData['size'] = $this->data['size'];
        }
        $lesson = new Lesson();
        $lesson->fill($lessonData);
        if (!isAuthorized("submanager", false, $lesson)) {
            throw new Exception("Ders güncelleme yetkiniz yok");
        }

        $lesson = $lessonController->updateLesson($lesson);
        $this->response = array(
            "msg" => "Ders başarıyla Güncellendi.",
            "status" => "success",

        );
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteLessonAction(): void
    {
        $lesson = (new Lesson())->find($this->data['id']) ?: throw new Exception("Ders bulunamadı");
        $currentUser = (new UserController())->getCurrentUser();
        if (!isAuthorized("submanager", false, $lesson)) {
            throw new Exception("Bu dersi silme yetkiniz yok");
        }
        /**
         * Hoca altında yetki ve dersin sahibi değilse
         */
        if ($currentUser->id != $lesson->lecturer_id and isAuthorized("lecturer", true)) {
            throw new Exception("Bu dersi silme yetkiniz yok");
        }
        (new LessonController())->delete($lesson->id);

        $this->response = array(
            "msg" => "Ders Başarıyla Silindi.",
            "status" => "success",
        );
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function combineLessonAction(): void
    {
        if (key_exists('parent_lesson_id', $this->data) and key_exists('child_lesson_id', $this->data)) {
            $lessonController = new LessonController();
            if (!isAuthorized('submanager')) {
                throw new Exception("Ders birleştirme yetkiniz yok");
            }
            $lessonController->combineLesson($this->data['parent_lesson_id'], $this->data['child_lesson_id']);
            $this->response = array(
                "msg" => "Dersler Başarıyla birleştirildi.",
                "status" => "success",
                "redirect" => "self"
            );
        } else throw new Exception("Birleştirmek için dersler belirtilmemiş");

        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteParentLessonAction(): void
    {
        if (key_exists("id", $this->data)) {
            if (!isAuthorized('submanager')) {
                throw new Exception("Ders birşeltirmesi kaldırma yetkiniz yok");
            }
            $lessonController = new LessonController();
            $lessonController->deleteParentLesson($this->data['id']);
            $this->response = array(
                "msg" => "Ders birleştirmesi başarıyla kaldırıldı.",
                "status" => "success",
                "redirect" => "self"
            );
        } else throw new Exception("Bağlantısı silinecek dersin id numarası gelirtilmemiş");

        $this->sendResponse();
    }

    /*
     * Classrooms Ajax Actions
     */
    /**
     * @throws Exception
     */
    public function addClassroomAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Yeni derslik oluşturma yetkiniz yok");
        }
        $classroomController = new ClassroomController();
        $classroomController->saveNew($this->data);

        $this->response = array(
            "msg" => "Derslik başarıyla eklendi.",
            "status" => "success",
        );
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateClassroomAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Derslik güncelleme yetkiniz yok");
        }

        $classroomController = new ClassroomController();
        $classroomData = $this->data;
        $classroom = new Classroom();
        $classroom->fill($classroomData);
        $classroom = $classroomController->updateClassroom($classroom);
        $this->response = array(
            "msg" => "Derslik başarıyla Güncellendi.",
            "status" => "success",
        );
        $this->sendResponse();
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
        $this->sendResponse();
    }

    /*
     * Departments Ajax Actions
     */
    /**
     * @throws Exception
     */
    public function addDepartmentAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Yeni Bölüm oluşturma yetkiniz yok");
        }

        $departmentController = new DepartmentController();
        $departmentController->saveNew($this->data);
        $this->response = array(
            "msg" => "Bölüm başarıyla eklendi.",
            "status" => "success",
        );
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateDepartmentAction(): void
    {
        $departmentController = new DepartmentController();
        $departmentData = $this->data;
        $department = new Department();
        $department->fill($departmentData);
        if (!isAuthorized("submanager", false, $department)) {
            throw new Exception("Bölüm Güncelleme yetkiniz yok");
        }

        $departmentController->updateDepartment($department);

        $this->response = array(
            "msg" => "Bölüm başarıyla Güncellendi.",
            "status" => "success",
        );
        $this->sendResponse();
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
        $this->sendResponse();
    }

    /*
     * Programs Ajax Actions
     */
    /**
     * @throws Exception
     */
    public function addProgramAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Yeni Program oluşturma yetkiniz yok");
        }
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
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateProgramAction(): void
    {
        $programController = new ProgramController();
        $programData = $this->data;
        $program = new Program();
        $program->fill($programData);
        if (!isAuthorized("submanager", false, $program)) {
            throw new Exception("Program güncelleme yetkiniz yok");
        }

        $programId = $programController->updateProgram($program);

        $this->response = array(
            "msg" => "Program Başarıyla Güncellendi.",
            "status" => "success",
        );
        $this->sendResponse();
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
        $this->sendResponse();
    }

    public function getProgramsListAction($department_id): void
    {
        $programController = new ProgramController();
        $programs = $programController->getProgramsList($department_id);
        $this->response['status'] = "success";
        $this->response['programs'] = $programs;
        $this->sendResponse();
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
        if (isset($this->data['only_table'])) {
            $only_table = $this->data['only_table'] ?? false;
            unset($this->data['only_table']);
        }
        $schedulesHTML = $scheduleController->getSchedulesHTML($this->data, $only_table);
        $this->response['status'] = "success";
        $this->response['HTML'] = $schedulesHTML;
        $this->sendResponse();
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
        $this->sendResponse();
    }

    /**
     * Front-end tarafında her bir saat için yapılan çakışma kontrolünün back-end kısmında yapılmasını sağlar
     * tek bir saat için kontrol yapılıyor.
     * @throws Exception
     */
    public function checkBackEndLessonCrashAction(): void
    {
        //todo key exists kontrolleri birleştirilebilir
        if (!key_exists("lesson_hours", $this->data) or !key_exists("time_start", $this->data)) {
            throw new Exception("Ders saati yada program saati yok");
        }
        if (!key_exists("semester", $this->data)) {
            $this->data['semester'] = getSettingValue('semester');
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data['academic_year'] = getSettingValue("academic_year");
        }
        $filters = [
            "type" => "lesson",// Programın türü lesson yada exam todo datadan al
            "time_start" => $this->data['time_start'],
            "day" => "day" . $this->data['day_index'],
            "lesson_hours" => $this->data['lesson_hours'],
            "semester_no" => trim($this->data['semester_no']),
            "semester" => $this->data['semester'],
            "academic_year" => $this->data['academic_year'],
        ];
        $scheduleController = new ScheduleController();
        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
            $lecturer = $lesson->getLecturer();
            $classroom = (new Classroom())->get()->where(["name" => trim($this->data['classroom_name'])])->first();
            // bağlı dersleri alıyoruz
            $lessons = (new Lesson())->get()->where(["parent_lesson_id" => $lesson->id])->all();
            //bağlı dersler listesine ana dersi ekliyoruz
            array_unshift($lessons, $lesson);

            $isCrashed = false;

            foreach ($lessons as $child) {
                /*
                 * Ders çakışmalarını kontrol etmek için kullanılacak olan filtreler
                 */
                $crashFilters = array_merge($filters, [
                    //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                    "owners" => [
                        "program" => $child->program_id,
                        "user" => $lecturer->id,
                        "lesson" => $child->id
                    ],//sıralama yetki kontrolü için önemli
                ]);
                /**
                 * Uzem Sınıfı değilse çakışma kontrolüne dersliği de ekle
                 * Bu aynı zamanda Uzem derslerinin programının uzem sınıfına kaydedilmemesini sağlar. Bu sayede unique hatası da oluşmaz
                 */
                if (!is_null($classroom) and $classroom->type != 3) {
                    $crashFilters['owners']['classroom'] = $classroom->id;
                }

                if ($scheduleController->checkScheduleCrash($crashFilters)) {
                    $isCrashed = true;
                } else {
                    $isCrashed = false;
                    break;
                }
            }

            if ($isCrashed) {// çakışma yok ise
                $this->response['status'] = "success";
                $this->response['msg'] = "Çakışma yok";
            }
        }

        $this->sendResponse();
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
        if (!key_exists("lesson_hours", $this->data) or !key_exists("time_start", $this->data)) {
            throw new Exception("Ders saati yada program saati yok");
        }
        if (!key_exists("semester", $this->data)) {
            $this->data['semester'] = getSettingValue('semester');
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data['academic_year'] = getSettingValue("academic_year");
        }
        $filters = [
            "type" => "lesson",// Programın türü lesson yada exam todo datadan al
            "time_start" => $this->data['time_start'],
            "day" => "day" . $this->data['day_index'],
            "lesson_hours" => $this->data['lesson_hours'],
            "semester_no" => trim($this->data['semester_no']),
            "semester" => $this->data['semester'],
            "academic_year" => $this->data['academic_year'],
        ];
        $scheduleController = new ScheduleController();
        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
            $lecturer = $lesson->getLecturer();
            $classroom = (new Classroom())->get()->where(["name" => trim($this->data['classroom_name'])])->first();
            // bağlı dersleri alıyoruz
            $lessons = (new Lesson())->get()->where(["parent_lesson_id" => $lesson->id])->all();
            //bağlı dersler listesine ana dersi ekliyoruz
            array_unshift($lessons, $lesson);

            $isCrashed = false;
            foreach ($lessons as $child) {
                /*
                * Ders çakışmalarını kontrol etmek için kullanılacak olan filtreler
                */
                $crashFilters = array_merge($filters, [
                    //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                    "owners" => [
                        "program" => $child->program_id,
                        "user" => $lecturer->id,
                        "lesson" => $child->id
                    ],//sıralama yetki kontrolü için önemli
                ]);
                /**
                 * Uzem Sınıfı değilse çakışma kontrolüne dersliği de ekle
                 * Bu aynı zamanda Uzem derslerinin programının uzem sınıfına kaydedilmemesini sağlar. Bu sayede unique hatası da oluşmaz
                 */
                if (!is_null($classroom) and $classroom->type != 3) {
                    $crashFilters['owners']['classroom'] = $classroom->id;
                }

                if ($scheduleController->checkScheduleCrash($crashFilters)) {
                    $isCrashed = true;
                } else {
                    $isCrashed = false;
                    break;
                }
            }

            if ($isCrashed) {// çakışma yok ise
                /**
                 * birden fazla saat eklendiğinde başlangıç saati ve saat bilgisine göre saatleri dizi olarak dindürür
                 *
                 */
                $timeArray = $scheduleController->generateTimesArrayFromText($this->data['time_start'], $this->data['lesson_hours']);
                /*
                 * her bir saat için ayrı ekleme yapılacak
                 */
                foreach ($timeArray as $time) {
                    if (count($lessons) > 1) {
                        if (!isAuthorized('submanager')) {
                            throw new Exception("Birleştirilmiş dersleri düzenleme yetkiniz yok");
                        }
                    }
                    foreach ($lessons as $child) {
                        /**
                         * @var Lesson $child
                         */
                        $scheduleFilters = array_merge($filters, [
                            //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                            "owners" => [
                                "program" => $child->program_id,
                                "lesson" => $child->id
                            ],//sıralama yetki kontrolü için önemli);
                        ]);
                        /**
                         * Uzem Sınıfı değilse ve asıl ders ise çakışma kontrolüne dersliği de ekle
                         * Bu aynı zamanda Uzem derslerinin programının uzem sınıfına kaydedilmemesini sağlar. Bu sayede unique hatası da oluşmaz
                         */
                        if ($classroom->type != 3 and is_null($child->parent_lesson_id)) {
                            $scheduleFilters['owners']['classroom'] = $classroom->id;
                        }
                        //sadece asıl dersin bilgisi kullanıcıya eklenecek
                        $scheduleFilters["owners"]["user"] = is_null($child->parent_lesson_id) ? $lesson->getLecturer()->id : null;
                        /**
                         * veri tabanına eklenecek gün verisi
                         */
                        $day = [
                            "lesson_id" => $child->id,
                            "classroom_id" => $classroom->id,
                            "lecturer_id" => $lecturer->id,
                        ];
                        if (!$child->IsScheduleComplete()) {
                            $schedule = new Schedule();
                            /*
                             * Bir program kaydı yapılırken kullanıcı, sınıf, program ve ders için birer kayıt yapılır.
                             * Bu değerler için döngü oluşturuluyor
                             */
                            foreach ($scheduleFilters['owners'] as $owner_type => $owner_id) {
                                if (is_null($owner_id)) continue;// child lesson ise owner_id null olduğundan atlanacak
                                $schedule->fill([
                                    "type" => "lesson",//todo datadan al
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
                        } else {
                            throw new Exception("Bu dersin programı zaten planlanmış. Ders saatinden fazla ekleme yapılamaz");
                        }
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
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function saveSchedulePreferenceAction(): void
    {
        $scheduleController = new ScheduleController();
        $filters = [
            "type" => "lesson",
            "semester" => getSettingValue("semester"),
            "academic_year" => getSettingValue("academic_year"),
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
        $this->sendResponse();
    }

    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     * @return void
     * @throws Exception
     */
    public function checkLecturerScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        if (!key_exists("semester", $this->data)) {
            $this->data["semester"] = getSettingValue("semester");
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data["academic_year"] = getSettingValue("academic_year");
        }

        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
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
                    if ($rowIndex === false) {
                        continue; // bu saat tabloda yoksa atla
                    }
                    for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {//day0-4
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
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function checkClassroomScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        if (!key_exists("semester", $this->data)) {
            $this->data["semester"] = getSettingValue("semester");
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data["academic_year"] = getSettingValue("academic_year");
        }

        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
            $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];

            $classrooms = (new Classroom())->get()->where(['type' => ['in' => $classroom_type]])->all();
            $this->response["classrooms"] = $classrooms;
            /**
             * Hiç bir derslik için uygun olmayan hücreler
             */
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
            $classroomIds = [];
            foreach ($classrooms as $classroom) {
                $classroomIds[] = $classroom->id;
                $filters = [
                    "owner_type" => "classroom",
                    "owner_id" => $classroom->id,
                    "type" => "lesson",
                    "semester" => $this->data['semester'],
                    "academic_year" => $this->data['academic_year'],
                ];
                $classroomSchedules = $scheduleController->getListByFilters($filters);

                if (count($classroomSchedules) > 0) {// dersliğin herhangi bir programı var ise
                    foreach ($classroomSchedules as $classroomSchedule) {
                        $rowIndex = array_search($classroomSchedule->time, $tableRows);
                        if ($rowIndex === false) {
                            continue; // bu saat tabloda yoksa atla
                        }
                        for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {//day0-4
                            if (!is_null($classroomSchedule->{"day" . $i})) { // derslik programında hoca programında olduğu gibi true yada false tanımlaması olmadığından null kontrolü yeterli
                                $unavailableCells[$rowIndex + 1][$i + 1][$classroom->id] = true; //ilk satır günler olduğu için +1, ilk sütun saatlar olduğu için+1
                            }
                        }
                    }
                }
            }
            $result = [];

            foreach ($unavailableCells as $rowKey => $row) {
                foreach ($row as $colKey => $classrooms) {
                    $hasAllClassrooms = true;

                    foreach ($classroomIds as $id) {
                        if (!isset($classrooms[$id])) {
                            $hasAllClassrooms = false;
                            break;
                        }
                    }

                    if ($hasAllClassrooms) {
                        if (!isset($result[$rowKey])) {
                            $result[$rowKey] = [];
                        }
                        $result[$rowKey][$colKey] = true;
                    }
                }
            }

            //$this->response = array("status" => "success", "msg" => "", "unavailableCells" => $unavailableCells);
            $this->response["status"] = "success";
            $this->response["msg"] = "";
            $this->response["unavailableCells"] = $result;

        }
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function checkProgramScheduleAction()
    {
        $scheduleController = new ScheduleController();
        if (!key_exists("semester", $this->data)) {
            $this->data["semester"] = getSettingValue("semester");
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data["academic_year"] = getSettingValue("academic_year");
        }

        if (key_exists("lesson_id", $this->data)) {
            $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
            $unavailableCells = [];
            $program = $lesson->getProgram();
            $filters = [
                "owner_type" => "program",
                "owner_id" => $program->id,
                "type" => "lesson",
                "semester" => $this->data['semester'],
                "semester_no" => $lesson->semester_no,
                "academic_year" => $this->data['academic_year'],
            ];
            $programSchedules = $scheduleController->getListByFilters($filters);
            if (count($programSchedules) > 0) {
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
                foreach ($programSchedules as $lessonSchedule) {
                    $rowIndex = array_search($lessonSchedule->time, $tableRows);
                    if ($rowIndex === false) {
                        continue; // bu saat tabloda yoksa atla
                    }
                    for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {//day0-4
                        if (is_array($lessonSchedule->{"day" . $i})) {
                            $unavailableCells[$rowIndex + 1][$i + 1] = true; //ilk satır günler olduğu için +1, ilk sütun saatlar olduğu için+1
                        }
                    }
                }
            }

            $this->response['status'] = "success";
            $this->response["msg"] = "";
            $this->response["unavailableCells"] = $unavailableCells;
        } else {
            $this->response = [
                "msg" => "Programın tüm saatleri müsait",
                "status" => "success"
            ];
        }
        $this->sendResponse();
    }

    /**
     * Ders programından veri silmek için gerekli kontrolleri yapar
     * @return void
     * @throws Exception
     * Gerekli bilgiler
     * - owner_type? (yoksa tüm tipler işleme alınır)
     * - semester? (yoksa ayarlardan alınır)
     * - academic_year? (yoksa ayarlardan alınır)
     * - lesson_id
     * - classroom_name //todo id ile işlem yapılmalı
     * - lecturer_id
     * - day_index
     * - semester_no
     * - time silinecek dersin bulunduğu saat
     * - day_index dersin silineceği gün
     * - type sınav(exam) yada ders(lesson) programı
     */
    public function deleteScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        if (!key_exists("semester", $this->data)) {
            $this->data["semester"] = getSettingValue("semester");
        }
        if (!key_exists("academic_year", $this->data)) {
            $this->data["academic_year"] = getSettingValue("academic_year");
        }

        if (!key_exists("owner_type", $this->data)) {
            //owner_type yok ise tüm owner_type'lar için döngü oluşturulacak
            $owners = [];
            if (key_exists("lesson_id", $this->data) and key_exists("classroom_name", $this->data)) {
                $lesson = (new Lesson())->find($this->data['lesson_id']) ?: throw new Exception("Ders bulunamadı");
                $lecturer = (new User())->find($this->data['lecturer_id']) ?: throw new Exception("Hoca bulunamadı");
                $classroom = (new Classroom())->get()->where(["name" => trim($this->data['classroom_name'])])->first();
                // bağlı dersleri alıyoruz
                $lessons = (new Lesson())->get()->where(["parent_lesson_id" => $lesson->id])->all();
                //bağlı dersler listesine ana dersi ekliyoruz
                array_unshift($lessons, $lesson);
                foreach ($lessons as $child) {
                    //set Owners
                    $owners['program'] = $child->program_id;
                    $owners["user"] = is_null($child->parent_lesson_id) ? $lecturer->id : null;
                    $owners['lesson'] = $child->id;
                    $owners["classroom"] = $classroom->id;
                    $day = [
                        "lesson_id" => $child->id,
                        "classroom_id" => $classroom->id,
                        "lecturer_id" => $lecturer->id,
                    ];
                    foreach ($owners as $owner_type => $owner_id) {
                        if (is_null($owner_id)) continue; // child lesson ise owner_id null olduğundan atlanacak
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
                }
            } else {
                throw new Exception("Owner_type belirtilmediğinde lesson_id ve classroom_name belirtilmelidir");
            }
        } else {// owner_type belirtilmişse sadece o type için işlem yapılacak
            /**
             * Burada null coalescing operatörü (??) ile eksik dizin hatalarını önlüyoruz ve sonra array_filter ile boş değerleri temizliyoruz.
             * todo buradaki yapı tüm filtrelere uygulanabilir
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
                "day" => $this->data["day"] ?? null,
            ], function ($value) {
                return $value !== null && $value !== '';
            });
            $scheduleController->deleteSchedule($filters);
        }
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function exportScheduleAction(): void
    {
        $filters = $this->data;
        if (!key_exists('type', $filters)) {
            throw new Exception("Dışarı aktarma işlemi için tür seçilmemiş.");
        }
        if (!key_exists('owner_type', $filters)) {
            throw new Exception("Dışarı aktarma işlemi için ders programı sahibi seçilmemiş.");
        }
        $importExportManager = new ImportExportManager();
        $importExportManager->exportSchedule($filters);
    }

    /*
     * Setting Actions
     */

    /**
     * @throws Exception
     */
    public function saveSettingsAction(): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Bu işlemi yapmak için yetkiniz yok");
        }
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
        $this->sendResponse();
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
        $this->sendResponse();
    }

    public function importLessonsAction(): void
    {
        $importExportManager = new ImportExportManager($this->files, $this->data);
        $result = $importExportManager->importLessonsFromExcel();
        $this->response['status'] = "success";
        $this->response['msg'] = sprintf("%d Ders oluşturuldu,%d Ders güncellendi. %d hatalı kayıt var", $result['added'], $result['updated'], $result['errorCount']);
        $this->response['errors'] = $result['errors'];
        $this->response['addedLessons'] = $result['addedLessons'];
        $this->response['updatedLessons'] = $result['updatedLessons'];
        $this->sendResponse();
    }
}