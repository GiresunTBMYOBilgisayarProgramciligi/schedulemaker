<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Enums\ExamType;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Schedule\AvailabilityService;
use App\Services\Schedule\ScheduleService;
use Exception;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;
use App\Validators\Schedule\ScheduleViewFilterValidator;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\ScheduleItemData;
use App\DTOs\SaveScheduleResult;
use App\Services\Schedule\LessonScheduleService;
use App\Services\Schedule\ExamScheduleService;
use App\Services\Schedule\ConflictService;
use App\Services\Export\ExporterFactory;
use App\Validators\Schedule\ScheduleAvailabilityFilterValidator;
use App\Validators\Schedule\ScheduleConflictFilterValidator;
use App\Validators\Schedule\ScheduleExportFilterValidator;
use App\Validators\ScheduleItemValidator;
use App\Repositories\ScheduleRepository;
use App\Models\ScheduleItem;
use App\Helpers\ScheduleViewHelper;
use App\Core\Gate;
use App\Enums\OwnerType;

class ScheduleController extends Controller
{
    protected string $modelName = "App\Models\Schedule";

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Sadece kullanılabilir dersler listesinin HTML çıktısını hazırlar
     * @param array $requestData
     * @param bool $preference_mode
     * @return string
     * @throws Exception
     */
    public function getAvailableLessonsHTML(array $requestData = [], bool $preference_mode = false): string
    {
        $dto = (new ScheduleViewFilterValidator())->getDTO($requestData, "availableLessons");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır
        if (in_array($dto->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value, OwnerType::LESSON->value])) {
            $data = $dto->toArray();
            $data['semester_no'] = null;
            $dto = ScheduleFilterDTO::fromArray($data);
        }

        $scheduleService = new ScheduleService();
        $schedule = $scheduleService->getOrCreateSchedule($dto);
        
        Gate::authorize('update', $schedule);

        $availableLessons = (new AvailabilityService())->availableLessons($schedule, $preference_mode);

        return View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => false,
            'preference_mode' => $preference_mode,
            'owner_type' => $dto->owner_type
        ]);
    }

    /**
     * Dönem numarasına göre birleştirilmiş yada her bir dönem için Schedule Card oluşturur
     * @param array $requestData
     * @param bool $only_table
     * @return string
     * @throws Exception
     */
    public function getSchedulesHTML(array $requestData = [], bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        $dto = (new ScheduleViewFilterValidator())->getDTO($requestData, "getSchedulesHTML");
        
        $scheduleService = new ScheduleService();
        $schedule = $scheduleService->getOrCreateSchedule($dto);
        Gate::authorize('view', $schedule, "Programı görüntüleme yetkiniz yok");

        $HTMLOut = "";

        if ($dto->semester_no !== null) {
            // birleştirilmiş dönem veya tek dönem
            $HTMLOut .= ScheduleViewHelper::prepareScheduleCard($dto, $only_table, $preference_mode, $no_card);
        } elseif (in_array($dto->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value, OwnerType::LESSON->value])) {
            // Hoca, Derslik ve Ders programları için tek bir genel program oluşturulur
            $HTMLOut .= ScheduleViewHelper::prepareScheduleCard($dto, $only_table, $preference_mode, $no_card);
        } else {
            $currentSemesters = getSemesterNumbers($dto->semester);
            foreach ($currentSemesters as $semester_no) {
                $data = $dto->toArray();
                $data['semester_no'] = $semester_no;
                $specificDto = ScheduleFilterDTO::fromArray($data);
                $HTMLOut .= ScheduleViewHelper::prepareScheduleCard($specificDto, $only_table, $preference_mode, $no_card);
            }
        }

        return $HTMLOut;
    }

    /**
     * Gelen verilere göre Program HTML çıktısını oluşturur (Ajax endpoint wrapper)
     * @param array $requestData
     * @return array Response dizisi
     */
    public function getSchedulesHTMLResponse(array $requestData): array
    {
        $only_table = false;
        if (isset($requestData['only_table'])) {
            $only_table = $requestData['only_table'] === "true";
            unset($requestData['only_table']);
        }
        $preference_mode = false;
        if (isset($requestData['preference_mode'])) {
            $preference_mode = $requestData['preference_mode'] === "true";
            unset($requestData['preference_mode']);
        }
        $no_card = false;
        if (isset($requestData['no_card'])) {
            $no_card = $requestData['no_card'] === "true";
            unset($requestData['no_card']);
        }
        
        $schedulesHTML = $this->getSchedulesHTML($requestData, $only_table, $preference_mode, $no_card);
        
        return [
            'status' => "success",
            'HTML' => $schedulesHTML
        ];
    }

    /**
     * Sadece kullanılabilir dersler listesinin HTML çıktısını döndürür (Ajax endpoint wrapper)
     * @param array $requestData
     * @return array Response dizisi
     */
    public function getAvailableLessonsHTMLResponse(array $requestData): array
    {
        $preference_mode = false;
        if (isset($requestData['preference_mode'])) {
            $preference_mode = $requestData['preference_mode'] === "true";
            unset($requestData['preference_mode']);
        }
        
        $html = $this->getAvailableLessonsHTML($requestData, $preference_mode);
        
        return [
            'status' => "success",
            'HTML' => $html
        ];
    }

    /********************************
     * KAYIT VE GÜNCELLEME İŞLEMLERİ
     ********************************/

    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     *
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return array
     * @throws Exception
     */

    /**
     * @throws \Exception
     */
    
    /**
     * Ders programı öğelerini kaydeder (Ajax endpoint wrapper)
     * 
     * gelen item verilerine göre ilk olarak çakışan item kontrol edilir checkScheduleCrashAction ile yapılan yeterli olmaz preferred item kontrolü ve düzenlemesi burada yapılmalı
     * çakışan item'in prefered olup olmadığı kontrol edilir. 
     * perefered item saat aralıkları kontrol edilir. eklenecek itemin saat aralıkları ile çakışan kısmı silinir. (silme işlemi start ve end time güncellemesi ile yapılır)
     * çakışan kısım prefered değil ise çakışma hatası verilir.
     * çakışan kısım yoksa item kaydedilir.
     * 
     * @param array $requestData AJAX'tan gelen $_POST / $_GET dizisi
     * @return array Response dizisi
     */
    public function saveScheduleItems(array $requestData): array
    {
        
        $items = json_decode($requestData['items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => "error",
                "msg" => "Geçersiz veri formatı"
            ];
        }

        $dtos = (new ScheduleItemValidator())->getBatchDTO($items);

        if (count($dtos) > 0) {
            $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', clone $schedule);
            }
        }

        $this->logger()->debug("Using LessonScheduleService::saveScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->saveScheduleItems($dtos);
        
        return [
            "status" => "success",
            "createdIds" => $result->createdIds,
        ];
    }

    /**
     * Ders programı öğelerini taşıma isteğini (tek işlemde sil ve ekle) işler
     */
    public function moveScheduleItems(array $requestData): array
    {
        $items = json_decode($requestData['items'] ?? '[]', true);
        $deletedItems = json_decode($requestData['deleted_items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => "error",
                "msg" => "Geçersiz veri formatı"
            ];
        }

        $dtos = (new ScheduleItemValidator())->getBatchDTO($items);
        $deletedDtos = (new ScheduleItemValidator())->getBatchDTO($deletedItems);

        // Update yetki kontrolü
        if (count($dtos) > 0) {
            $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) Gate::authorize('update', clone $schedule);
        } elseif (count($deletedDtos) > 0) {
            $schedule = (new ScheduleRepository())->find($deletedDtos[0]->scheduleId);
            if ($schedule) Gate::authorize('update', clone $schedule);
        }

        $this->logger()->debug("Using LessonScheduleService::moveScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->moveScheduleItems($dtos, $deletedDtos);
        
        return [
            "status" => "success",
            "createdIds" => $result->createdIds,
        ];
    }

    /**
     * @throws \Exception
     */
    public function deleteScheduleItems(array $requestData): array
    {
        $items = json_decode($requestData['items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => "error",
                "msg" => "Geçersiz veri formatı"
            ];
        }

        $dtos = (new ScheduleItemValidator())->getBatchDTO($items);

        if (count($dtos) > 0) {
            $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', clone $schedule);
            }
        }

        $this->logger()->debug("Using LessonScheduleService::deleteScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->deleteScheduleItems($dtos);
        return $result->toArray();
    }

    /**
     * Sınav programı öğelerini kaydeder
     */
    public function saveExamScheduleItems(array $requestData): array
    {
        $items = json_decode($requestData['items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => "error",
                "msg" => "Geçersiz veri formatı",
            ];
        }

        $dtos = (new ScheduleItemValidator())->getBatchDTO($items);

        if (count($dtos) > 0) {
            $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', clone $schedule);
            }
        }

        $this->logger()->debug("Using ExamScheduleService::saveExamScheduleItems", $this->logContext());
        $service = new ExamScheduleService();
        $createdIds = $service->saveExamScheduleItems($dtos);
        
        $createdItems = [];
        if (!empty($createdIds)) {
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
        }

        return [
            "status" => "success",
            "msg" => "Sınav programı başarıyla kaydedildi.",
            "createdIds" => $createdIds,
            "createdItems" => $createdItems,
        ];
    }

    /**
     * Sınav programı öğelerini taşıma isteği (tek işlemde sil ve ekle)
     */
    public function moveExamScheduleItems(array $requestData): array
    {
        $items = json_decode($requestData['items'] ?? '[]', true);
        $deletedItems = json_decode($requestData['deleted_items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => "error",
                "msg" => "Geçersiz veri formatı",
            ];
        }

        $dtos = (new ScheduleItemValidator())->getBatchDTO($items);
        $deletedDtos = (new ScheduleItemValidator())->getBatchDTO($deletedItems);

        if (count($dtos) > 0) {
            $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) Gate::authorize('update', clone $schedule);
        } elseif (count($deletedDtos) > 0) {
            $schedule = (new ScheduleRepository())->find($deletedDtos[0]->scheduleId);
            if ($schedule) Gate::authorize('update', clone $schedule);
        }

        $this->logger()->debug("Using ExamScheduleService::moveExamScheduleItems", $this->logContext());
        $service = new ExamScheduleService();
        $createdIds = $service->moveExamScheduleItems($dtos, $deletedDtos);
        
        $createdItems = [];
        if (!empty($createdIds)) {
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
        }

        return [
            "status" => "success",
            "createdIds" => $createdIds,
            "items" => $createdItems
        ];
    }



    /**
     * Müsait derslikleri getirir
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function getAvailableClassrooms(array $requestData): array
    {
        Gate::authorizeRole("department_head", false, "Uygun ders listesini almak için yetkiniz yok");
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "availableClassrooms");
        $service = new AvailabilityService();
        $classrooms = $service->availableClassrooms($dto->toArray()); // Servis güncellendiğinde toArray kalkacak
        return [
            'status' => 'success',
            'classrooms' => $classrooms
        ];
    }

    /**
     * Müsait gözetmenleri getirir
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function getAvailableObservers(array $requestData): array
    {
        Gate::authorizeRole("department_head", false, "Uygun gözetmen listesini almak için yetkiniz yok");
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "availableObservers");
        $service = new AvailabilityService();
        $observers = $service->availableObservers($dto->toArray()); // Servis güncellendiğinde toArray kalkacak
        return [
            'status' => 'success',
            'observers' => $observers
        ];
    }

    /**
     * Çakışma kontrolü yapar
     * @param array $requestData
     * @return array
     */
    public function checkScheduleCrash(array $requestData): array
    {
        $dto = (new ScheduleConflictFilterValidator())->getDTO($requestData, "checkScheduleCrash");
        $service = new ConflictService();
        $service->checkScheduleCrash($dto->toArray()); // Servis güncellendiğinde toArray kalkacak

        return ['status' => 'success'];
    }

    /**
     * ID değerine göre program bilgisini döndürür
     * @param array $requestData
     * @return array
     */
    public function getSchedule(array $requestData): array
    {
        if (key_exists('id', $requestData)) {
            $schedule = (new ScheduleRepository())->find($requestData['id']);
            if ($schedule) {
                Gate::authorize('view', $schedule, "Programı görüntüleme yetkiniz yok");
                return [
                    "status" => "success",
                    "schedule" => $schedule->getArray()
                ];
            } else {
                return [
                    "status" => "error",
                    "msg" => "Program bulunamadı"
                ];
            }
        } else {
            return [
                "status" => "error",
                "msg" => "ID belirtilmedi"
            ];
        }
    }

    /**
     * Hoca çakışma durumunu kontrol eder
     * @param array $requestData
     * @return array
     */
    public function checkLecturerSchedule(array $requestData): array
    {
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "checkLecturerScheduleAction");
        $availability = (new AvailabilityService())->getLecturerAvailability($dto->toArray());

        return [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $availability['unavailableCells'],
            "preferredCells" => $availability['preferredCells']
        ];
    }

    /**
     * Derslik çakışma durumunu kontrol eder
     * @param array $requestData
     * @return array
     */
    public function checkClassroomSchedule(array $requestData): array
    {
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "checkClassroomScheduleAction");
        $availability = (new AvailabilityService())->getClassroomAvailability($dto->toArray());

        return [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $availability['unavailableCells']
        ];
    }

    /**
     * Program çakışma durumunu kontrol eder
     * @param array $requestData
     * @return array
     */
    public function checkProgramSchedule(array $requestData): array
    {
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "checkProgramScheduleAction");
        $availability = (new AvailabilityService())->getProgramAvailability($dto->toArray());

        return [
            "status" => "success",
            "msg" => "",
            "unavailableCells" => $availability['unavailableCells']
        ];
    }

    /**
     * Excel program dışa aktarma
     * @param array $requestData
     * @throws Exception
     */
    public function exportSchedule(array $requestData): void
    {
        $dto = (new ScheduleExportFilterValidator())->getDTO($requestData, "exportScheduleAction");

        $showOptions = [
            'show_code'     => $dto->show_code ?? true,
            'show_lecturer' => $dto->show_lecturer ?? true,
            'show_program'  => $dto->show_program ?? true,
            'show_observer' => $dto->show_observer ?? true,
        ];

        $exporter = ExporterFactory::create($dto->toArray(), 'excel');
        $exporter->export($dto->toArray(), $showOptions);
    }

    /**
     * ICS program dışa aktarma
     * @param array $requestData
     * @throws Exception
     */
    public function exportScheduleIcs(array $requestData): void
    {
        $dto = (new ScheduleExportFilterValidator())->getDTO($requestData, "exportScheduleIcsAction");

        $showOptions = [
            'show_observer' => $dto->show_observer ?? true,
        ];

        $exporter = ExporterFactory::create($dto->toArray(), 'ics');
        $exporter->export($dto->toArray(), $showOptions);
    }
}