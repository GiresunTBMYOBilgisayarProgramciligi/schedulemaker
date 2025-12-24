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
use App\Helpers\FilterValidator;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
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
        // todo burada oturum kontrolü yapmak ana sayfadaki oturumsuz işlemleri engelleyebiliyor. yapılmazsa ne olur?
        //if (!$userController->isLoggedIn()) {
        //  throw new Exception("Oturumunuz sona ermiş. Lütfen tekrar giriş yapın", 330);
        //} else $userController->getCurrentUser();
        // todo ki zaten else kısmında getCurrent user ı tek başına çağırarak işe yaramadığını görmüş oluyorum ama düşünmek lazım
    }

    /**
     * Gelen isteğin ajax isteği olup olmadığını kontrol eder
     * @return bool
     */
    public function checkAjax(): bool
    {
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
        } else
            throw new Exception("Birleştirmek için dersler belirtilmemiş");

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
        } else
            throw new Exception("Bağlantısı silinecek dersin id numarası gelirtilmemiş");

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
        $department = (new Department())->find($this->data['id']);
        if (!isAuthorized("submanager", false, $department)) {
            throw new Exception("Bölüm Güncelleme yetkiniz yok");
        }

        $departmentController = new DepartmentController();
        $departmentController->updateDepartment($this->data);

        $this->response["msg"] = "Bölüm başarıyla Güncellendi.";
        $this->response["status"] = "success";

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
        $program = (new Program())->find($programData["id"]);
        if (!isAuthorized("submanager", false, $program)) {
            throw new Exception("Program güncelleme yetkiniz yok");
        }

        $programId = $programController->updateProgram($programData);

        $this->response = array(
            "msg" => "Program Başarıyla Güncellendi.",
            "updatedID" => $programId,
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

    /**
     * @throws Exception
     */
    public function getProgramsListAction($department_id): void
    {
        $programController = new ProgramController();
        $programs = $programController->getProgramsList(['department_id' => $department_id, 'active' => true]);
        $this->response['status'] = "success";
        $this->response['programs'] = $programs;
        $this->sendResponse();
    }

    /*
     * Schedules Ajax Actions
     */

    /**
     * Gelen verilere göre Program HTML çıktısını oluşturur
     * @throws Exception
     */
    public function getScheduleHTMLAction(): void
    {
        $scheduleController = new ScheduleController();
        $only_table = false;
        if (isset($this->data['only_table'])) {
            $only_table = $this->data['only_table'] === "true";
            unset($this->data['only_table']);
        }
        $schedulesHTML = $scheduleController->getSchedulesHTML($this->data, $only_table);
        $this->response['status'] = "success";
        $this->response['HTML'] = $schedulesHTML;
        $this->sendResponse();
    }

    /**
     * Ders programı seçiminde Eklenen derse uygun olan sınıf listesini hazırlar.
     * @throws Exception
     */
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
     * todo 
     * Ders programı seçiminde Eklenen derse uygun olan gözetmen listesini hazırlar.
     * @throws Exception
     */
    public function getAvailableObserversForScheduleAction(): void
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Uygun gözetmen listesini almak için yetkiniz yok");
        }
        $scheduleController = new ScheduleController();
        $observers = $scheduleController->availableObservers($this->data);
        $this->response['status'] = "success";
        $this->response['observers'] = $observers;
        $this->sendResponse();
    }

    /**
     * todo
     * @throws Exception
     */
    public function checkScheduleCrashAction(): void
    {
        $scheduleController = new ScheduleController();
        $scheduleController->checkScheduleCrash($this->data);

        $this->response['status'] = "success";
        $this->sendResponse();
    }

    /**
     * ID değerine göre program bilgisini döndürür
     * @return void
     * @throws Exception
     */
    public function getScheduleAction(): void
    {
        if (key_exists('id', $this->data)) {
            $schedule = (new Schedule())->find($this->data['id']);
            if ($schedule) {
                $this->response = array(
                    "status" => "success",
                    "schedule" => $schedule->getArray()
                );
            } else {
                $this->response = array(
                    "status" => "error",
                    "msg" => "Program bulunamadı"
                );
            }
        } else {
            $this->response = array(
                "status" => "error",
                "msg" => "ID belirtilmedi"
            );
        }
        $this->sendResponse();
    }
    /**
     * gelen item verilerine göre ilk olarak çakışan item kontrol edilir checkScheduleCrashAction ile yapılan yeterli olmaz preferred item kontrolü ve düzenlemesi burada yapılmalı
     * çakışan item'in prefered olup olmadığı kontrol edilir. 
     * perefered item saat aralıkları kontrol edilir. eklenecek itemin saat aralıkları ile çakışan kısmı silinir. (silme işlemi start ve end time güncellemesi ile yapılır)
     * çakışan kısım prefered değil ise çakışma hatası verilir.
     * çakışan kısım yoksa item kaydedilir.
     */
    public function saveScheduleItemAction(): void
    {
        //$this->logger()->debug("Save ScheduleItemAction Data: ", ['data' => $this->data]);

        $scheduleController = new ScheduleController();
        $items = json_decode($this->data['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response = array(
                "status" => "error",
                "msg" => "Geçersiz veri formatı"
            );
            $this->sendResponse();
            return;
        }

        try {
            $createdIds = $scheduleController->saveScheduleItems($items);
            if (!empty($createdIds)) {
                $this->response = array(
                    "status" => "success",
                    "msg" => "Program başarıyla kaydedildi.",
                    "createdIds" => $createdIds
                );
            }
        } catch (Exception $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            $this->response = array(
                "status" => "error",
                "msg" => $e->getMessage()
            );
        }
        $this->sendResponse();
    }


    /**
     * todo
     * @throws Exception
     */
    public function saveSchedulePreferenceAction(): void
    {
        $scheduleController = new ScheduleController();
        $filters = $scheduleController->validator->validate($this->data, "saveSchedulePreferenceAction");
        $currentSemesters = getSemesterNumbers($filters["semester"]);
        /**
         * Her iki dönem için de tercih kaydediliyor.
         */
        foreach ($currentSemesters as $semester_no) {
            $filters['semester_no'] = $semester_no;
            $filters['day' . $filters['day_index']] = $filters['day'][0];
            // todo   $savedId = $scheduleController->saveNew(array_diff_key($filters, array_flip(["day_index", "day"])));
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
        $filters = $scheduleController->validator->validate($this->data, "checkLecturerScheduleAction");

        $lesson = (new Lesson())->where(['id' => $filters['lesson_id']])->with(['lecturer'])->first()
            ?: throw new Exception("Ders bulunamadı");
        $lecturer = $lesson->lecturer;

        // Ayarlara göre slotları (satırları) oluştur
        $type = in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $duration = getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
        $break = getSettingValue('break', $type, $type === 'exam' ? 0 : 10);
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $slots = [];
        $start = new \DateTime('08:00');
        $end = new \DateTime('17:00');
        while ($start < $end) {
            $slotStart = clone $start;
            $slotEnd = (clone $start)->modify("+$duration minutes");
            $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i')];
            $start = (clone $slotEnd)->modify("+$break minutes");
        }

        $unavailableCells = [];
        $preferredCells = [];

        // Hocaya ait o dönemdeki TÜM programları (ders, sınav, tercih vb.) kontrol et
        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'user',
            'owner_id' => $lecturer->id,
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
        ])->with(['items'])->all();

        foreach ($schedules as $schedule) {
            foreach ($schedule->items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                foreach ($slots as $rowIndex => $slot) {
                    if (($itemStart < $slot['end']) && ($slot['start'] < $itemEnd)) {
                        if ($item->status === 'preferred') {
                            $preferredCells[$rowIndex + 1][$item->day_index + 1] = true;
                        } else {
                            // unavailable, single, group vb. durumlar hoca için "dolu/uygun değil" demektir
                            $unavailableCells[$rowIndex + 1][$item->day_index + 1] = true;
                        }
                    }
                }
            }
        }

        $this->response = [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $unavailableCells,
            "preferredCells" => $preferredCells
        ];
        $this->sendResponse();
    }

    /**
     * 
     * @throws Exception
     */
    public function checkClassroomScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        $filters = $scheduleController->validator->validate($this->data, "checkClassroomScheduleAction");

        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Ders bulunamadı");
        $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
        $classrooms = (new Classroom())->get()->where(['type' => ['in' => $classroom_type]])->all();

        // Ayarlara göre slotları (satırları) oluştur
        $type = in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $duration = getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
        $break = getSettingValue('break', $type, $type === 'exam' ? 0 : 10);
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $slots = [];
        $start = new \DateTime('08:00');
        $end = new \DateTime('17:00');
        while ($start < $end) {
            $slotStart = clone $start;
            $slotEnd = (clone $start)->modify("+$duration minutes");
            $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i')];
            $start = (clone $slotEnd)->modify("+$break minutes");
        }

        /**
         * Hangi hücrede hangi dersliğin DOLU olduğunu takip eder
         * $classroomOccupancy[rowIndex][dayIndex][classroomId] = true
         */
        $classroomOccupancy = [];
        $classroomIds = array_column($classrooms, 'id');
        //$this->logger()->debug("Classroom IDs: ", ["classroomIds" => $classroomIds]);

        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'classroom',
            'owner_id' => ['in' => $classroomIds],
            'type' => $filters['type'],
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
        ])->with(['items'])->all();
        //$this->logger()->debug("Schedules: ", ['schedules' => $schedules]);

        foreach ($schedules as $schedule) {
            //$this->logger()->debug("Schedule Items: ", ['scheduleItems' => $schedule->items]);
            foreach ($schedule->items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                foreach ($slots as $rowIndex => $slot) {
                    // Çakışma kontrolü: (itemStart < slotEnd) && (slotStart < itemEnd)
                    if (($itemStart < $slot['end']) && ($slot['start'] < $itemEnd)) {
                        // Bu slot ve bu günde bu derslik dolu
                        $classroomOccupancy[$rowIndex + 1][$item->day_index + 1][$schedule->owner_id] = true;
                    }
                }
            }
        }

        // Eğer bir hücrede TÜM derslikler doluysa o hücreyi "unavailable" (kullanılamaz) işaretle
        $result = [];
        foreach ($slots as $rowIndex => $slot) {
            $rowKey = $rowIndex + 1;
            for ($dayIndex = 0; $dayIndex <= $maxDayIndex; $dayIndex++) {
                $colKey = $dayIndex + 1;
                $hasAvailable = false;

                foreach ($classroomIds as $id) {
                    if (!isset($classroomOccupancy[$rowKey][$colKey][$id])) {
                        $hasAvailable = true;
                        break;
                    }
                }

                if (!$hasAvailable) {
                    if (!isset($result[$rowKey])) {
                        $result[$rowKey] = [];
                    }
                    $result[$rowKey][$colKey] = true;
                }
            }
        }

        $this->response["status"] = "success";
        $this->response["msg"] = "";
        $this->response["unavailableCells"] = $result;

        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function checkProgramScheduleAction(): void
    {
        $scheduleController = new ScheduleController();
        $filters = $scheduleController->validator->validate($this->data, "checkProgramScheduleAction");

        $lesson = (new Lesson())->where([
            'id' => $filters['lesson_id'],
        ])->with(['program'])->first() ?: throw new Exception("Ders bulunamadı");
        $program = $lesson->program;

        // Ayarlara göre slotları (satırları) oluştur
        $type = in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $duration = getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
        $break = getSettingValue('break', $type, $type === 'exam' ? 0 : 10);
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $slots = [];
        $start = new \DateTime('08:00');
        $end = new \DateTime('17:00');
        while ($start < $end) {
            $slotStart = clone $start;
            $slotEnd = (clone $start)->modify("+$duration minutes");
            $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i')];
            $start = (clone $slotEnd)->modify("+$break minutes");
        }

        $unavailableCells = [];

        // Programın o dönemdeki TÜM programlarını (ders, sınav vb.) kontrol et
        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'program',
            'owner_id' => $program->id,
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
            'semester_no' => $lesson->semester_no
        ])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $schedule->id])->all();
            foreach ($items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                foreach ($slots as $rowIndex => $slot) {
                    if (($itemStart < $slot['end']) && ($slot['start'] < $itemEnd)) {
                        $unavailableCells[$rowIndex + 1][$item->day_index + 1] = true;
                    }
                }
            }
        }

        $this->response = [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $unavailableCells
        ];
        $this->sendResponse();
    }

    /**
     * Ders programından item silme işlemi
     * @return void
     * @throws Exception
     */
    public function deleteScheduleItemsAction(): void
    {
        $this->logger()->debug("Delete ScheduleItemsAction Data: ", ['data' => $this->data]);

        $scheduleController = new ScheduleController();
        $items = json_decode($this->data['items'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response = [
                "status" => "error",
                "msg" => "Geçersiz veri formatı"
            ];
            $this->sendResponse();
            return;
        }

        try {
            $result = $scheduleController->deleteScheduleItems($items);
            $this->response = array_merge(["status" => "success"], $result);
        } catch (Exception $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            $this->response = [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
        $this->sendResponse();
    }

    /**
     * todo
     * @throws Exception
     */
    public function exportScheduleAction(): void
    {
        $filters = (new FilterValidator())->validate($this->data, "exportScheduleAction");

        $importExportManager = new ImportExportManager();
        $importExportManager->exportSchedule($filters);
    }

    /**
     * todo
     * Takvim (ICS) dışa aktarma
     * @throws Exception
     */
    public function exportScheduleIcsAction(): void
    {
        $filters = (new FilterValidator())->validate($this->data, "exportScheduleIcsAction");
        $importExportManager = new ImportExportManager();
        $importExportManager->exportScheduleIcs($filters);
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