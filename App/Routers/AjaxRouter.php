<?php

namespace App\Routers;

use App\Services\ClassroomService;
use App\Controllers\DepartmentController;
use App\Services\LessonService;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;
use App\Controllers\SettingsController;
use App\Services\UserService;
use App\Core\ImportExportManager;
use App\Core\Router;
use App\Services\ScheduleService;
use App\Services\ExamService;
use App\Services\ConflictService;
use App\Services\AvailabilityService;
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
use App\Core\Gate;

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
        // Oturumdaki kullanıcıyı bir kez çekip tüm action metodlarında kullanıma hazırla
        $this->currentUser = (new \App\Controllers\UserController())->getCurrentUser() ?: null;
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
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                // JSON body — $_POST boş gelir, php://input'tan oku
                $body = file_get_contents('php://input');
                $this->data = json_decode($body, true) ?? [];
            } else {
                $this->data = $_POST;
            }
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
        if (is_null($userData['department_id']) or $userData['department_id'] == '0') {
            unset($userData['department_id']);
        }
        if (is_null($userData['program_id']) or $userData['program_id'] == '0') {
            unset($userData['program_id']);
        }
        Gate::authorizeRole("submanager", false, "Kullanıcı oluşturma yetkiniz yok");
        (new UserService())->saveNew($userData);
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
        $userData = $this->data;
        if (isset($userData['department_id']) and $userData['department_id'] == '0') {
            $userData['department_id'] = null;
        }
        if (isset($userData['program_id']) and $userData['program_id'] == '0') {
            $userData['program_id'] = null;
        }
        $new_user = new User();
        $new_user->fill($userData);
        Gate::authorize("update", $new_user, "Kullanıcı bilgilerini güncelleme yetkiniz yok");
        (new UserService())->updateUser($new_user);
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
        $user = (new User())->find($this->data['id']);
        Gate::authorize("delete", $user, "Kullanıcı Silme yetkiniz yok");
        $user->delete();

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
        $lessonData = $this->data;
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
        Gate::authorize("create", $new_lesson, "Yeni Ders oluşturma yetkiniz yok");

        $lesson = (new LessonService())->saveNew($new_lesson);
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
        $lessonData = $this->data;

        // Hoca yetkisindeki kullanıcı yalnızca kendi dersinin mevcut/sınıf türünü güncelleyebilir.
        // Güvenlik için lecturer_id POST'tan değil DB'den alınır (disabled alan POST'a gelmez).
        $lessonFromDb = (new Lesson())->find((int)($lessonData['id'] ?? 0));
        $isLecturerOwnLesson = Gate::allowsRole("lecturer", true)
            && $lessonFromDb
            && $this->currentUser
            && $lessonFromDb->lecturer_id == $this->currentUser->id;

        if ($isLecturerOwnLesson) {
            // DB'den gelen nesneyi doğrudan kullan — sadece izin verilen alanları güncelle.
            // new Lesson() + fill() yapılırsa diğer alanlar (code vb.) null kalır ve UNIQUE hatası oluşur.
            Gate::authorize("update", $lessonFromDb, "Ders güncelleme yetkiniz yok");
            $lessonFromDb->size           = $this->data['size'];
            $lessonFromDb->classroom_type = $this->data['classroom_type'] ?? $lessonFromDb->classroom_type;
            (new LessonService())->updateLesson($lessonFromDb);
        } else {
            // Admin/yetkili güncelleme: bölüm ve program zorunlu
            if (!isset($lessonData['department_id']) || $lessonData['department_id'] == '0') {
                $lessonData['department_id'] = null;
            }
            if (!isset($lessonData['program_id']) || $lessonData['program_id'] == '0') {
                $lessonData['program_id'] = null;
            }
            if (empty($lessonData['department_id'])) {
                throw new Exception("Bölüm Seçmelisiniz");
            }
            if (empty($lessonData['program_id'])) {
                throw new Exception("Program Seçmelisiniz");
            }
            $lesson = new Lesson();
            $lesson->fill($lessonData);
            Gate::authorize("update", $lesson, "Ders güncelleme yetkiniz yok");
            (new LessonService())->updateLesson($lesson);
        }

        $this->response = array(
            "msg"    => "Ders başarıyla Güncellendi.",
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
        Gate::authorize("delete", $lesson, "Bu dersi silme yetkiniz yok");
        $lesson->delete();

        $this->response = array(
            "msg" => "Ders Başarıyla Silindi.",
            "status" => "success",
        );
        $this->sendResponse();
    }

    /**
     * Ders birleştirme önizleme — DB değişikliği yapmaz.
     * Saat farkı varsa parent'ın schedule item'larını bireysel saat dilimleri olarak döner.
     * @throws Exception
     */
    public function previewCombineLessonAction(): void
    {
        $parentId = (int) ($this->data['parent_lesson_id'] ?? 0);
        $childId  = (int) ($this->data['child_lesson_id'] ?? 0);

        if (!$parentId || !$childId) {
            throw new Exception("Birleştirmek için dersler belirtilmemiş");
        }
        Gate::authorizeRole("submanager", false, "Ders birleştirme yetkiniz yok");

        $parentLesson = (new Lesson())->find($parentId)
            ?: throw new Exception("Üst ders bulunamadı");
        $childLesson  = (new Lesson())->find($childId)
            ?: throw new Exception("Bağlanacak ders bulunamadı");

        $hoursDiff = $parentLesson->hours - $childLesson->hours;

        if ($hoursDiff <= 0) {
            $this->response = ['needs_confirmation' => false];
            $this->sendResponse();
            return;
        }

        // Parent'ın ders programı var mı?
        $parentSchedule = (new Schedule())
            ->get()
            ->where(['owner_type' => 'lesson', 'owner_id' => $parentId])
            ->with(['items'])
            ->first();

        if (!$parentSchedule || empty($parentSchedule->items)) {
            $this->response = ['needs_confirmation' => false];
            $this->sendResponse();
            return;
        }

        // Ayarlardan ders süresi ve mola bilgisini al
        $duration = (int) getSettingValue('duration', 'lesson', 50); // dakika
        $break    = (int) getSettingValue('break', 'lesson', 10);    // dakika

        $dayNames = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

        $slots = [];
        foreach ($parentSchedule->items as $item) {
            if (in_array($item->status, ['unavailable', 'preferred'])) {
                continue;
            }
            $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                  ?: \DateTime::createFromFormat('H:i', $item->start_time);
            if (!$start) continue;

            // İtem kaç saat içeriyor?
            $slotStart = clone $start;
            $slotIndex = 0;

            // Saatleri tek tek üret: süre+mola adımlarıyla
            while (true) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$duration} minutes");

                $slots[] = [
                    'id'         => "{$item->id}_{$slotIndex}",
                    'item_id'    => $item->id,
                    'slot_index' => $slotIndex,
                    'day_name'   => $dayNames[$item->day_index] ?? "Gün {$item->day_index}",
                    'day_index'  => $item->day_index,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time'   => $slotEnd->format('H:i'),
                ];

                // Bir sonraki slot başlangıcı: mola ekle
                $slotStart = clone $slotEnd;
                $slotStart->modify("+{$break} minutes");
                $slotIndex++;

                // Item'in bitiş saatini geçti mi? (mola süresini tolere et)
                $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                        ?: \DateTime::createFromFormat('H:i', $item->end_time);
                if (!$itemEnd || $slotStart >= $itemEnd) break;
            }
        }

        // Gün ve saate göre sırala
        usort($slots, fn($a, $b) => $a['day_index'] <=> $b['day_index'] ?: $a['start_time'] <=> $b['start_time']);

        $this->response = [
            'needs_confirmation' => true,
            'hours_diff'         => $hoursDiff,
            'parent_hours'       => $parentLesson->hours,
            'child_hours'        => $childLesson->hours,
            'items'              => $slots,
        ];
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function combineLessonAction(): void
    {
        if (key_exists('parent_lesson_id', $this->data) and key_exists('child_lesson_id', $this->data)) {
            Gate::authorizeRole("submanager", false, "Ders birleştirme yetkiniz yok");

            // items_to_remove: ["itemId_slotIndex", ...] formatını parse et
            // Örn: ["606_2", "606_3"] → {606: [2, 3]}
            $rawRemove = (array) ($this->data['items_to_remove'] ?? []);
            $slotsToSkip = []; // [item_id => [slot_index, ...]]
            foreach ($rawRemove as $entry) {
                [$itemId, $slotIdx] = explode('_', (string) $entry, 2);
                $slotsToSkip[(int)$itemId][] = (int)$slotIdx;
            }

            (new LessonService())->combineLesson(
                (int) $this->data['parent_lesson_id'],
                (int) $this->data['child_lesson_id'],
                $slotsToSkip
            );
            $this->response = array(
                "msg"      => "Dersler Başarıyla birleştirildi.",
                "status"   => "success",
                "redirect" => "self"
            );
        } else {
            throw new Exception("Birleştirmek için dersler belirtilmemiş");
        }
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteParentLessonAction(): void
    {
        if (key_exists("id", $this->data)) {
            Gate::authorizeRole("submanager", false, "Ders birşeltirmesi kaldırma yetkiniz yok");
            (new LessonService())->deleteParentLesson((int) $this->data['id']);
            $this->response = array(
                "msg" => "Ders birleştirmesi başarıyla kaldırıldı.",
                "status" => "success",
                "redirect" => "self"
            );
        } else {
            throw new Exception("Bağlantısı silinecek dersin id numarası gelirtilmemiş");
        }
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
        Gate::authorize("create", Classroom::class, "Yeni derslik oluşturma yetkiniz yok");
        (new ClassroomService())->saveNew($this->data);
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
        $classroomData = $this->data;
        $classroom = new Classroom();
        $classroom->fill($classroomData);
        Gate::authorize("update", $classroom, "Derslik güncelleme yetkiniz yok");
        (new ClassroomService())->updateClassroom($classroom);
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
        $classroom = (new Classroom())->find($this->data['id']) ?: throw new Exception("Derslik bulunamadı");
        Gate::authorize("delete", $classroom, "Derslik silme yetkiniz yok");
        $classroom->delete();

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
        Gate::authorize("create", Department::class, "Yeni Bölüm oluşturma yetkiniz yok");

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
        Gate::authorize("update", $department, "Bölüm Güncelleme yetkiniz yok");

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
        $department = (new Department())->find($this->data['id']);
        Gate::authorize("delete", $department, "Bölüm silme yetkiniz yok");
        $department->delete();

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
        Gate::authorize("create", Program::class, "Yeni Program oluşturma yetkiniz yok");
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
        Gate::authorize("update", $program, "Program güncelleme yetkiniz yok");

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
        $program = (new Program())->find($this->data['id']) ?: throw new Exception("Program bulunamadı");
        Gate::authorize("delete", $program, "Program silme yetkiniz yok");
        $program->delete();

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
        $preference_mode = false;
        if (isset($this->data['preference_mode'])) {
            $preference_mode = $this->data['preference_mode'] === "true";
            unset($this->data['preference_mode']);
        }
        $no_card = false;
        if (isset($this->data['no_card'])) {
            $no_card = $this->data['no_card'] === "true";
            unset($this->data['no_card']);
        }
        $schedulesHTML = $scheduleController->getSchedulesHTML($this->data, $only_table, $preference_mode, $no_card);
        $this->response['status'] = "success";
        $this->response['HTML'] = $schedulesHTML;
        $this->sendResponse();
    }

    /**
     * Sadece kullanılabilir dersler listesinin HTML çıktısını döndürür
     * @throws Exception
     */
    public function getAvailableLessonsHTMLAction(): void
    {
        $scheduleController = new ScheduleController();
        $preference_mode = false;
        if (isset($this->data['preference_mode'])) {
            $preference_mode = $this->data['preference_mode'] === "true";
            unset($this->data['preference_mode']);
        }
        $html = $scheduleController->getAvailableLessonsHTML($this->data, $preference_mode);
        $this->response['status'] = "success";
        $this->response['HTML'] = $html;
        $this->sendResponse();
    }

    /**
     * Ders veya sınav programı seçiminde eklenen derse uygun derslik listesini hazırlar.
     * Schedule tipi exam ise UZEM hariç tümü; ders ise classroom_type filtresi uygulanır.
     * @throws Exception
     */
    public function getAvailableClassroomForScheduleAction(): void
    {
        Gate::authorizeRole("department_head", false, "Uygun ders listesini almak için yetkiniz yok");
        $filters = (new FilterValidator())->validate($this->data, "availableClassrooms");
        $service = new AvailabilityService();
        $classrooms = $service->availableClassrooms($filters);
        $this->response['status'] = "success";
        $this->response['classrooms'] = $classrooms;
        $this->sendResponse();
    }

    /**
     * Sınav atamalarında müsait gözetmenlerin listesini hazırlar.
     * @throws Exception
     */
    public function getAvailableObserversForScheduleAction(): void
    {
        Gate::authorizeRole("department_head", false, "Uygun gözetmen listesini almak için yetkiniz yok");
        $filters = (new FilterValidator())->validate($this->data, "availableObservers");
        $service = new ExamService();
        $observers = $service->availableObservers($filters);
        $this->response['status'] = "success";
        $this->response['observers'] = $observers;
        $this->sendResponse();
    }

    /**
     * Ders veya sınav programına eklenmek istenen item için çakışma kontrolü yapar.
     * Her iki program tipi için de çalışır; iç mantık schedule.type ve assignments'a göre ayrım yapar.
     * @throws Exception
     */
    public function checkScheduleCrashAction(): void
    {
        try {
            $filters = (new FilterValidator())->validate($this->data, "checkScheduleCrash");
            $service = new ConflictService();
            $service->checkScheduleCrash($filters);

            $this->response['status'] = "success";
        } catch (\Throwable $e) {
            $this->logger()->error('checkScheduleCrash failed', ['exception' => (string) $e, 'payload' => $this->data]);
            $msg = $e->getMessage();
            $msgArray = explode("\n", $msg);
            $this->response = [
                "status" => "error",
                "msg" => count($msgArray) > 1 ? $msgArray : $msg,
            ];
        }
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
                // Canlı Güncelleme (Live Update) için oluşturulan öğelerin tam listesini topla
                $createdItems = [];
                foreach ($createdIds as $groupedIds) {
                    foreach ($groupedIds as $ownerType => $ids) {
                        foreach ($ids as $id) {
                            $item = (new ScheduleItem())->find($id);
                            if ($item) {
                                $createdItems[] = $item->getArray();
                            }
                        }
                    }
                }

                $this->response = array(
                    "status" => "success",
                    "msg" => "Program başarıyla kaydedildi.",
                    "createdIds" => $createdIds,
                    "createdItems" => $createdItems
                );
            }
        } catch (\Throwable $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            $msg = $e->getMessage();
            $msgArray = explode("\n", $msg);
            $this->response = array(
                "status" => "error",
                "msg" => count($msgArray) > 1 ? $msgArray : "Sistem Hatası: " . $msg
            );
        }
        $this->sendResponse();
    }

    /**
     * Sınav programı kaydetme isteği
     */
    public function saveExamScheduleItemAction(): void
    {
        $items = json_decode($this->data['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response = [
                "status" => "error",
                "msg" => "Geçersiz veri formatı",
            ];
            $this->sendResponse();
            return;
        }

        try {
            $service = new ExamService();
            $createdIds = $service->saveExamScheduleItems($items);
            if (!empty($createdIds)) {
                $createdItems = [];
                foreach ($createdIds as $groupedIds) {
                    foreach ($groupedIds as $ownerType => $ids) {
                        foreach ($ids as $id) {
                            $item = (new ScheduleItem())->find($id);
                            if ($item) {
                                $createdItems[] = $item->getArray();
                            }
                        }
                    }
                }

                $this->response = [
                    "status" => "success",
                    "msg" => "Sınav programı başarıyla kaydedildi.",
                    "createdIds" => $createdIds,
                    "createdItems" => $createdItems,
                ];
            }
        } catch (\Throwable $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            $msg = $e->getMessage();
            $msgArray = explode("\n", $msg);
            $this->response = [
                "status" => "error",
                "msg" => count($msgArray) > 1 ? $msgArray : "Sistem Hatası: " . $msg,
            ];
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
        $filters = (new FilterValidator())->validate($this->data, "checkLecturerScheduleAction");
        $availability = (new AvailabilityService())->getLecturerAvailability($filters);

        $this->response = [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $availability['unavailableCells'],
            "preferredCells" => $availability['preferredCells']
        ];
        $this->sendResponse();
    }

    /**
     * 
     * @throws Exception
     */
    public function checkClassroomScheduleAction(): void
    {
        $filters = (new FilterValidator())->validate($this->data, "checkClassroomScheduleAction");
        $availability = (new AvailabilityService())->getClassroomAvailability($filters);

        $this->response["status"] = "success";
        $this->response["msg"] = "";
        $this->response["unavailableCells"] = $availability['unavailableCells'];

        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function checkProgramScheduleAction(): void
    {
        $filters = (new FilterValidator())->validate($this->data, "checkProgramScheduleAction");
        $availability = (new AvailabilityService())->getProgramAvailability($filters);

        $this->response = [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $availability['unavailableCells']
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
            $service = new ScheduleService();
            $result = $service->deleteScheduleItems($items);
            $this->response = $result->toArray();
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
        Gate::authorizeRole("submanager", false, "Bu işlemi yapmak için yetkiniz yok");
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

    /**
     * @throws Exception
     */
    public function clearLogsAction(): void
    {
        (new SettingsController())->clearLogsAction();
        $this->response['status'] = "success";
        $this->response['msg'] = "Loglar başarıyla temizlendi";
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