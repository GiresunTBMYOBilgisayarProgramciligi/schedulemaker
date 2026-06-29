<?php

namespace App\Routers;

use App\Middlewares\AuthMiddleware;
use App\Controllers\UserController;
use App\Controllers\ClassroomController;

use App\Controllers\DepartmentController;
use App\Services\LessonService;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;


use App\Controllers\SettingsController;
use App\Core\Router;
use App\Services\Schedule\ScheduleService;
use App\Services\Schedule\ExamScheduleService;
use App\Services\Schedule\ConflictService;
use App\Services\Schedule\AvailabilityService;
use App\Services\Export\ExporterFactory;
use App\Services\Import\UserImporter;
use App\Services\Import\LessonImporter;
use App\Validators\Schedule\ScheduleAvailabilityFilterValidator;
use App\Validators\Schedule\ScheduleConflictFilterValidator;
use App\Validators\Schedule\ScheduleExportFilterValidator;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use function App\Helpers\getSettingValue;
use App\Core\Gate;
use App\Attributes\AuthRequired;
use App\Attributes\PublicAction;

#[AuthRequired]
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
        // İstemci tarafında oturum bilgisini kullanabilmek için
        // Oturumdaki kullanıcıyı bir kez çekip tüm action metodlarında kullanıma hazırla
        $this->currentUser = AuthMiddleware::user();
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
        $this->response = (new UserController())->store($this->data);
        $this->sendResponse();
    }

    /**
     * Ajax ile gelen verilerden oluşturduğu User Modeli ile verileri günceller
     * @return void
     * @throws Exception
     */
    public function updateUserAction(): void
    {
        $this->response = (new UserController())->update($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteUserAction(): void
    {
        $this->response = (new UserController())->destroy($this->data);
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
        $this->response = (new LessonController())->store($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateLessonAction(): void
    {
        $this->response = (new LessonController())->update($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteLessonAction(): void
    {
        $this->response = (new LessonController())->destroy($this->data);
        $this->sendResponse();
    }

    /**todo bu ne işe yarıyor kullanılıyor mu?
     * 
     * Ders birleştirme önizleme — DB değişikliği yapmaz.
     * Saat farkı varsa parent'ın schedule item'larını bireysel saat dilimleri olarak döner.
     * @throws Exception
     */
    public function previewCombineLessonAction(): void
    {
        $this->response = (new LessonController())->previewCombine($this->data);
        $this->sendResponse();
    }
    /**
     * @throws Exception
     */
    public function combineLessonAction(): void
    {
        $this->response = (new LessonController())->combine($this->data);
        $this->sendResponse();
    }
    /**
     * @throws Exception
     */
    public function deleteParentLessonAction(): void
    {
        $this->response = (new LessonController())->deleteParentLesson($this->data);
        $this->sendResponse();
    }
    /**
     * Sınav birleştirme — farklı hocaların derslerini sınav için birleştirir.
     * @throws Exception
     */
    public function combineExamLessonAction(): void
    {
        $this->response = (new LessonController())->combineExamLesson($this->data);
        $this->sendResponse();
    }
    /**
     * Sınav birleştirme bağlantısını kaldırır.
     * @throws Exception
     */
    public function deleteExamParentLessonAction(): void
    {
        if (!key_exists("id", $this->data)) {
            throw new Exception("Bağlantısı silinecek dersin id numarası belirtilmemiş");
        }
        Gate::authorizeRole("department_head", false, "Sınav birleştirmesi kaldırma yetkiniz yok");
        (new LessonService())->deleteExamParentLesson((int) $this->data['id']);
        $this->response = [
            "msg"      => "Sınav birleştirmesi başarıyla kaldırıldı.",
            "status"   => "success",
            "redirect" => "self"
        ];
        $this->sendResponse();
    }

    /**
     * Sınav birleştirme için aranabilir ders listesi (TomSelect AJAX).
     * Aynı akademik yıl ve dönemdeki dersleri döner.
     * @throws Exception
     */
    public function getExamCombinableLessonsAction(): void
    {
        Gate::authorizeRole("department_head", false, "Sınav birleştirme listesini almak için yetkiniz yok");

        $lessonId = (int) ($this->data['lesson_id'] ?? 0);
        $search = trim($this->data['search'] ?? '');

        if (!$lessonId) {
            throw new Exception("Ders ID belirtilmemiş");
        }

        $currentLesson = (new Lesson())->where(['id' => $lessonId])
            ->with(['examChildLessons', 'examParentLesson'])
            ->first()
            ?: throw new Exception("Ders bulunamadı");

        // Aynı akademik yıl ve dönemdeki dersleri al
        $filters = [
            'semester'      => $currentLesson->semester,
            'academic_year' => $currentLesson->academic_year,
            '!id'           => $currentLesson->id,
        ];

        $query = (new Lesson())->get()->where($filters)
            ->with(['program', 'lecturer', 'examParentLesson']);

        $lessons = $query->all();

        // Zaten bağlı olanları ve kendisini filtrele
        $existingChildIds = array_map(fn($c) => $c->id, $currentLesson->examChildLessons);
        
        $result = [];
        foreach ($lessons as $lesson) {
            // Kendisi zaten bir exam child ise atla (zaten birleştirilmiş)
            if ($lesson->exam_parent_lesson_id && $lesson->exam_parent_lesson_id !== $currentLesson->id) {
                continue;
            }
            // Zaten bu derse bağlı olanları atla
            if (in_array($lesson->id, $existingChildIds)) {
                continue;
            }

            $label = $lesson->getFullName(addCode: true, addProgram: true, addSize: true);

            // TomSelect arama filtresi
            if ($search !== '' && stripos($label, $search) === false) {
                continue;
            }

            $result[] = [
                'id'    => $lesson->id,
                'text'  => $label,
                'size'  => $lesson->size,
                'program' => $lesson->program->name ?? '',
            ];
        }

        $this->response = [
            'status'  => 'success',
            'lessons' => $result,
        ];
        $this->sendResponse();
    }

    /*
     * Classrooms Ajax Actions
     */
    
    public function addClassroomAction(): void
    {
        $this->response = (new ClassroomController())->store($this->data);
        $this->sendResponse();
    }

    public function updateClassroomAction(): void
    {
        $this->response = (new ClassroomController())->update($this->data);
        $this->sendResponse();
    }

    public function deleteClassroomAction(): void
    {
        $this->response = (new ClassroomController())->destroy($this->data);
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
        $this->response = (new DepartmentController())->store($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateDepartmentAction(): void
    {
        $this->response = (new DepartmentController())->update($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteDepartmentAction(): void
    {
        $this->response = (new DepartmentController())->destroy($this->data);
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
        $this->response = (new ProgramController())->store($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function updateProgramAction(): void
    {
        $this->response = (new ProgramController())->update($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function deleteProgramAction(): void
    {
        $this->response = (new ProgramController())->destroy($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    #[PublicAction]
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
    #[PublicAction]
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
        $filters = (new ScheduleAvailabilityFilterValidator())->sanitize($this->data, "availableClassrooms");
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
        $filters = (new ScheduleAvailabilityFilterValidator())->sanitize($this->data, "availableObservers");
        $service = new AvailabilityService();
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
            $filters = (new ScheduleConflictFilterValidator())->sanitize($this->data, "checkScheduleCrash");
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
        $this->response = (new ScheduleController())->saveScheduleItems($this->data);
        $this->sendResponse();
    }
    /**
     * Sınav programı kaydetme isteği
     */
    public function saveExamScheduleItemAction(): void
    {
        $this->response = (new ScheduleController())->saveExamScheduleItems($this->data);
        $this->sendResponse();
    }
    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     * @return void
     * @throws Exception
     */
    public function checkLecturerScheduleAction(): void
    {
        $filters = (new ScheduleAvailabilityFilterValidator())->sanitize($this->data, "checkLecturerScheduleAction");
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
        $filters = (new ScheduleAvailabilityFilterValidator())->sanitize($this->data, "checkClassroomScheduleAction");
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
        $filters = (new ScheduleAvailabilityFilterValidator())->sanitize($this->data, "checkProgramScheduleAction");
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
        $this->response = (new ScheduleController())->deleteScheduleItems($this->data);
        $this->sendResponse();
    }
    /**
     * @throws Exception
     */
    public function saveSettingsAction(): void
    {
        $this->response = (new SettingsController())->store($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function clearLogsAction(): void
    {
        $this->response = (new SettingsController())->clearLogs();
        $this->sendResponse();
    }

    /*
     * İmport ve Export
     */
    public function importUsersAction(): void
    {
        $uploadedFile = $this->files['file'] ?? null;
        if (!$uploadedFile) throw new Exception("Dosya yüklenmedi");

        $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
        $importer    = new UserImporter($spreadsheet);
        $result      = $importer->import();

        $this->response['status'] = "success";
        $this->response['msg']    = sprintf(
            "%d kullanıcı oluşturuldu,%d kullanıcı güncellendi. %d hatalı kayıt var",
            $result['added'], $result['updated'], $result['errorCount']
        );
        $this->response['errors'] = $result['errors'];
        $this->sendResponse();
    }

    public function importLessonsAction(): void
    {
        $uploadedFile = $this->files['file'] ?? null;
        if (!$uploadedFile) throw new Exception("Dosya yüklenmedi");

        $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
        $importer    = new LessonImporter($spreadsheet, $this->data);
        $result      = $importer->import();

        $this->response['status']         = "success";
        $this->response['msg']            = sprintf(
            "%d Ders oluşturuldu,%d Ders güncellendi. %d hatalı kayıt var",
            $result['added'], $result['updated'], $result['errorCount']
        );
        $this->response['errors']         = $result['errors'];
        $this->response['addedLessons']   = $result['addedLessons'];
        $this->response['updatedLessons'] = $result['updatedLessons'];
        $this->sendResponse();
    }

    /**
     * Excel / ICS program dışa aktarma — ExporterFactory ve ScheduleExporterInterface üzerinden çalışır.
     * @throws Exception
     */
    #[PublicAction]
    public function exportScheduleAction(): void
    {
        $filters = (new ScheduleExportFilterValidator())->sanitize($this->data, "exportScheduleAction");

        $showOptions = [
            'show_code'     => !isset($filters['show_code'])     || (string) $filters['show_code']     === '1',
            'show_lecturer' => !isset($filters['show_lecturer']) || (string) $filters['show_lecturer'] === '1',
            'show_program'  => !isset($filters['show_program'])  || (string) $filters['show_program']  === '1',
            'show_observer' => !isset($filters['show_observer']) || (string) $filters['show_observer'] === '1',
        ];

        $exporter = ExporterFactory::create($filters, 'excel');
        $exporter->export($filters, $showOptions);
    }

    /**
     * Takvim (ICS) dışa aktarma — ExporterFactory üzerinden çalışır.
     * @throws Exception
     */
    #[PublicAction]
    public function exportScheduleIcsAction(): void
    {
        $filters = (new ScheduleExportFilterValidator())->sanitize($this->data, "exportScheduleIcsAction");

        $showOptions = [
            'show_observer' => !isset($filters['show_observer']) || (string) ($filters['show_observer'] ?? '1') === '1',
        ];

        $exporter = ExporterFactory::create($filters, 'ics');
        $exporter->export($filters, $showOptions);
    }
}