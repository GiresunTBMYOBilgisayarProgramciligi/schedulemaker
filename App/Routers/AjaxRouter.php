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
use App\Services\Import\UserImporter;
use App\Services\Import\LessonImporter;
use App\Models\Lesson;
use App\Models\User;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    /**
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
        $this->response = (new LessonController())->deleteExamParentLesson($this->data);
        $this->sendResponse();
    }

    /**
     * Sınav birleştirme için aranabilir ders listesi (TomSelect AJAX).
     * Aynı akademik yıl ve dönemdeki dersleri döner.
     * @throws Exception
     */
    public function getExamCombinableLessonsAction(): void
    {
        $this->response = (new LessonController())->getExamCombinableLessons($this->data);
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
        $this->response = (new ProgramController())->getProgramsListResponse((int)$department_id);
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
        $this->response = (new ScheduleController())->getSchedulesHTMLResponse($this->data);
        $this->sendResponse();
    }

    /**
     * Sadece kullanılabilir dersler listesinin HTML çıktısını döndürür
     * @throws Exception
     */
    public function getAvailableLessonsHTMLAction(): void
    {
        $this->response = (new ScheduleController())->getAvailableLessonsHTMLResponse($this->data);
        $this->sendResponse();
    }

    /**
     * Ders veya sınav programı seçiminde eklenen derse uygun derslik listesini hazırlar.
     * Schedule tipi exam ise UZEM hariç tümü; ders ise classroom_type filtresi uygulanır.
     * @throws Exception
     */
    public function getAvailableClassroomForScheduleAction(): void
    {
        $this->response = (new ScheduleController())->getAvailableClassrooms($this->data);
        $this->sendResponse();
    }

    /**
     * Sınav atamalarında müsait gözetmenlerin listesini hazırlar.
     * @throws Exception
     */
    public function getAvailableObserversForScheduleAction(): void
    {
        $this->response = (new ScheduleController())->getAvailableObservers($this->data);
        $this->sendResponse();
    }

    /**
     * Ders veya sınav programına eklenmek istenen item için çakışma kontrolü yapar.
     * Her iki program tipi için de çalışır; iç mantık schedule.type ve assignments'a göre ayrım yapar.
     * @throws Exception
     */
    public function checkScheduleCrashAction(): void
    {
        $this->response = (new ScheduleController())->checkScheduleCrash($this->data);
        $this->sendResponse();
    }

    /**
     * ID değerine göre program bilgisini döndürür
     * @return void
     * @throws Exception
     */
    public function getScheduleAction(): void
    {
        $this->response = (new ScheduleController())->getSchedule($this->data);
        $this->sendResponse();
    }

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
     * Ders programı taşıma isteği (silme + kaydetme)
     */
    public function moveScheduleItemsAction(): void
    {
        $this->response = (new ScheduleController())->moveScheduleItems($this->data);
        $this->sendResponse();
    }
    
    /**
     * Sınav programı taşıma isteği (silme + kaydetme)
     */
    public function moveExamScheduleItemsAction(): void
    {
        $this->response = (new ScheduleController())->moveExamScheduleItems($this->data);
        $this->sendResponse();
    }
    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     * @return void
     * @throws Exception
     */
    public function checkLecturerScheduleAction(): void
    {
        $this->response = (new ScheduleController())->checkLecturerSchedule($this->data);
        $this->sendResponse();
    }

    /**
     * 
     * @throws Exception
     */
    public function checkClassroomScheduleAction(): void
    {
        $this->response = (new ScheduleController())->checkClassroomSchedule($this->data);
        $this->sendResponse();
    }

    /**
     * @throws Exception
     */
    public function checkProgramScheduleAction(): void
    {
        $this->response = (new ScheduleController())->checkProgramSchedule($this->data);
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
        $this->response = (new UserController())->importUsers($this->files);
        $this->sendResponse();
    }

    public function importLessonsAction(): void
    {
        $this->response = (new LessonController())->importLessons($this->files, $this->data);
        $this->sendResponse();
    }

    /**
     * Excel / ICS program dışa aktarma — ExporterFactory ve ScheduleExporterInterface üzerinden çalışır.
     * @throws Exception
     */
    #[PublicAction]
    public function exportScheduleAction(): void
    {
        (new ScheduleController())->exportSchedule($this->data);
    }

    /**
     * Takvim (ICS) dışa aktarma — ExporterFactory üzerinden çalışır.
     * @throws Exception
     */
    #[PublicAction]
    public function exportScheduleIcsAction(): void
    {
        (new ScheduleController())->exportScheduleIcs($this->data);
    }
}