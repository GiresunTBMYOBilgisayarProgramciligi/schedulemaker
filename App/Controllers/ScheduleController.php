<?php

namespace App\Controllers;

use App\Enums\PermissionType;

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
use App\DTOs\SaveScheduleResult;
use App\DTOs\ScheduleItemDTO;
use App\Services\Schedule\LessonScheduleService;
use App\Services\Schedule\ExamScheduleService;
use App\Services\Schedule\ConflictService;
use App\Services\Schedule\SchedulePublishService;
use App\Services\Export\ExporterFactory;
use App\Validators\Schedule\ScheduleAvailabilityFilterValidator;
use App\Validators\Schedule\ScheduleConflictFilterValidator;
use App\Validators\Schedule\ScheduleExportFilterValidator;
use App\Validators\ScheduleItemValidator;
use App\Validators\ToggleLockScheduleItemValidator;
use App\Exceptions\ValidationException;
use App\Repositories\ScheduleRepository;
use App\Models\ScheduleItem;
use App\Helpers\ScheduleViewHelper;
use App\Helpers\ScheduleLogHelper;
use App\Core\Gate;
use App\Middlewares\AuthMiddleware;
use App\Exceptions\AuthorizationException;
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
        
        Gate::authorize(PermissionType::UPDATE->value, $schedule);

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
        $is_published_only = isset($requestData['is_published']) && $requestData['is_published'] === "true";
        $dto = (new ScheduleViewFilterValidator())->getDTO($requestData, "getSchedulesHTML");
        
        // Non-program owners (User, Classroom, Lesson) never have semester_no
        if (in_array($dto->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value, OwnerType::LESSON->value])) {
            if ($dto->semester_no !== null) {
                $data = $dto->toArray();
                $data['semester_no'] = null;
                $dto = ScheduleFilterDTO::fromArray($data);
            }
        }

        $scheduleService = new ScheduleService();
        
        $schedule = null;
        if ($is_published_only) {
            $shouldCheckEarly = true;
            if ($dto->owner_type === OwnerType::PROGRAM->value && $dto->semester_no === null) {
                $shouldCheckEarly = false;
            }
            
            if ($shouldCheckEarly) {
                $schedule = (new ScheduleRepository())->findByOwnerAndPeriod(
                    $dto->owner_type,
                    $dto->owner_id,
                    $dto->academic_year,
                    $dto->semester,
                    $dto->type,
                    $dto->owner_type === OwnerType::PROGRAM->value ? $dto->semester_no : null
                );
                
                if (!$schedule || !Gate::check(PermissionType::VIEW->value, $schedule)) {
                    return "<div class='alert alert-info m-3'><i class='bi bi-info-circle me-2'></i>Yayınlanmış program bulunamadı.</div>";
                }
            }
        } else {
            // Only get or create schedule here if it is not a multi-semester program request
            if (!($dto->owner_type === OwnerType::PROGRAM->value && $dto->semester_no === null)) {
                $schedule = $scheduleService->getOrCreateSchedule($dto);
            }
        }
        
        if ($schedule !== null) {
            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                return "<div class='alert alert-info m-3'><i class='bi bi-info-circle me-2'></i>Program henüz yayınlanmadı.</div>";
            }
        }

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
                if ($is_published_only) {
                    $sch = (new ScheduleRepository())->findByOwnerAndPeriod(
                        $dto->owner_type,
                        $dto->owner_id,
                        $dto->academic_year,
                        $dto->semester,
                        $dto->type,
                        $semester_no
                    );
                    if (!$sch || !Gate::check(PermissionType::VIEW->value, $sch)) {
                        continue; // Skip this semester if not accessible/published
                    }
                }
                
                $data = $dto->toArray();
                $data['semester_no'] = $semester_no;
                $specificDto = ScheduleFilterDTO::fromArray($data);
                $HTMLOut .= ScheduleViewHelper::prepareScheduleCard($specificDto, $only_table, $preference_mode, $no_card);
            }
            if ($is_published_only && empty($HTMLOut)) {
                $HTMLOut = "<div class='alert alert-info m-3'><i class='bi bi-info-circle me-2'></i>Yayınlanmış program bulunamadı.</div>";
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
     * Gelen DTO'lar için yetki kontrolü yapar. Hoca programıysa ek olarak dersin yetkisini kontrol eder.
     * @param array $dtos
     */
    private function authorizeScheduleItemChanges(array $dtos): void
    {
        if (empty($dtos)) return;

        $schedule = (new ScheduleRepository())->find($dtos[0]->scheduleId);
        if (!$schedule) return;

        Gate::authorize(PermissionType::UPDATE->value, clone $schedule);

        if ($schedule->owner_type === OwnerType::USER->value) {
            $checkedLessons = [];
            foreach ($dtos as $dto) {
                if (empty($dto->lesson_id) || in_array($dto->lesson_id, $checkedLessons)) {
                    continue;
                }
                $lessonSchedule = clone $schedule;
                $lessonSchedule->owner_type = OwnerType::LESSON->value;
                $lessonSchedule->owner_id = $dto->lesson_id;
                
                Gate::authorize(PermissionType::UPDATE->value, clone $lessonSchedule);
                $checkedLessons[] = $dto->lesson_id;
            }
        }
    }

    /**
     * Ders programı öğelerini (ScheduleItems) kaydetme isteğini işler.
     * 
     * gelen item verilerine göre ilk olarak çakışan item kontrol edilir checkScheduleCrashAction ile yapılan yeterli olmaz preferred item kontrolü ve düzenlemesi burada yapılmalı
     * çakışan item'in prefered olup olmadığı kontrol edilir. 
     * perefered item saat aralıkları kontrol edilir. eklenecek itemin saat aralıkları ile çakışan kısmı silinir. (silme işlemi start ve end time güncellemesi ile yapılır)
     * çakışan kısım prefered değil ise çakışma hatası verilir.
     * çakışan kısım yoksa item kaydedilir.
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

        $this->authorizeScheduleItemChanges($dtos);

        $this->logger()->debug("Using LessonScheduleService::saveScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->saveScheduleItems($dtos);
        
        if (!$result->success) {
            return [
                "status" => "error",
                "msg" => $result->warnings[0] ?? "Program kaydedilirken bir hata oluştu."
            ];
        }

        foreach ($dtos as $dto) {
            ScheduleLogHelper::logAndRecordChange('save', 'eklendi/güncellendi', $dto, null, false);
        }

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
        $this->authorizeScheduleItemChanges(array_merge($dtos, $deletedDtos));

        $this->logger()->debug("Using LessonScheduleService::moveScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->moveScheduleItems($dtos, $deletedDtos);
        
        if (!$result->success) {
            return [
                "status" => "error",
                "msg" => $result->warnings[0] ?? "Program güncellenirken bir hata oluştu."
            ];
        }

        foreach ($dtos as $index => $dto) {
            $oldDto = $deletedDtos[$index] ?? null;
            ScheduleLogHelper::logAndRecordChange('move', 'taşındı', $dto, $oldDto, false);
        }

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

        $this->authorizeScheduleItemChanges($dtos);

        $this->logger()->debug("Using LessonScheduleService::deleteScheduleItems", $this->logContext());
        $service = new LessonScheduleService();
        $result = $service->deleteScheduleItems($dtos);
        
        if (!$result->success) {
            return [
                "status" => "error",
                "msg" => $result->warnings[0] ?? "Program silinirken bir hata oluştu."
            ];
        }

        foreach ($dtos as $dto) {
            ScheduleLogHelper::logAndRecordChange('delete', 'silindi', $dto, null, false);
        }
        
        return [
            "status" => "success",
            "msg" => "Başarıyla silindi.",
        ];
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

        $this->authorizeScheduleItemChanges($dtos);

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
            
            foreach ($dtos as $dto) {
                ScheduleLogHelper::logAndRecordChange('save', 'eklendi/güncellendi', $dto, null, true);
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
            if ($schedule) Gate::authorize(PermissionType::UPDATE->value, clone $schedule);
        } elseif (count($deletedDtos) > 0) {
            $schedule = (new ScheduleRepository())->find($deletedDtos[0]->scheduleId);
            if ($schedule) Gate::authorize(PermissionType::UPDATE->value, clone $schedule);
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
            
            foreach ($dtos as $index => $dto) {
                $oldDto = $deletedDtos[$index] ?? null;
                ScheduleLogHelper::logAndRecordChange('move', 'taşındı', $dto, $oldDto, true);
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
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "availableClassrooms");
        if ($dto->schedule_id) {
            $schedule = clone (new ScheduleRepository())->find($dto->schedule_id);
            if ($schedule) {
                Gate::authorize(PermissionType::UPDATE->value, $schedule, "Uygun derslik listesini almak için yetkiniz yok");
            }
        } else {
            Gate::authorizeRole("department_head", false, "Uygun derslik listesini almak için yetkiniz yok");
        }
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
        $dto = (new ScheduleAvailabilityFilterValidator())->getDTO($requestData, "availableObservers");
        if ($dto->schedule_id) {
            $schedule = clone (new ScheduleRepository())->find($dto->schedule_id);
            if ($schedule) {
                Gate::authorize(PermissionType::UPDATE->value, $schedule, "Uygun gözetmen listesini almak için yetkiniz yok");
            }
        } else {
            Gate::authorizeRole("department_head", false, "Uygun gözetmen listesini almak için yetkiniz yok");
        }
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
                Gate::authorize(PermissionType::VIEW->value, $schedule, "Programı görüntüleme yetkiniz yok");
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

    /**
     * Program öğesini kilitler veya kilidini açar.
     */
    public function toggleLockScheduleItem(array $requestData): array
    {
        $this->logger()->info("toggleLockScheduleItem request received", $this->logContext(['request_data' => $requestData]));

        try {
            $dto = (new ToggleLockScheduleItemValidator())->getDTO($requestData);
            
            // İlk öğenin ait olduğu program üzerinden yetki kontrolü yap
            $firstItemId = $dto->ids[0] ?? null;
            if ($firstItemId) {
                $item = (new ScheduleItem())->find($firstItemId);
                if ($item) {
                    $schedule = (new ScheduleRepository())->find($item->schedule_id);
                    if ($schedule) {
                        Gate::authorize(PermissionType::MANAGE_LOCK_SCHEDULE_ITEM->value, $schedule);
                    }
                }
            }

            $service = new ScheduleService();
            [$successCount, $finalState] = $service->toggleLockScheduleItems($dto);

            if ($successCount === 0) {
                return ["status" => "error", "msg" => "Öğeler bulunamadı veya güncellenemedi"];
            }

            return [
                "status" => "success",
                "msg" => $successCount > 1 
                    ? "$successCount öğe " . ($finalState ? "kilitlendi" : "kilidi açıldı")
                    : "Öğe " . ($finalState ? "kilitlendi" : "kilidi açıldı"),
                "is_locked" => $finalState
            ];
        } catch (ValidationException $e) {
            $this->logger()->warning("toggleLockScheduleItem validation failed", $this->logContext(['errors' => $e->getValidationErrors()]));
            return ["status" => "error", "msg" => implode(", ", $e->getValidationErrors())];
        } catch (Exception $e) {
            $this->logger()->error("toggleLockScheduleItem failed", $this->logContext(['error' => $e->getMessage()]));
            return ["status" => "error", "msg" => "İşlem sırasında hata oluştu: " . $e->getMessage()];
        }
    }

    /**
     * @throws Exception
     */
    public function togglePublishSchedule(array $requestData): array
    {
        if (!isset($requestData['id'])) {
            return ["status" => "error", "msg" => "Program ID belirtilmedi"];
        }

        $schedule = (new ScheduleRepository())->find($requestData['id']);
        if (!$schedule) {
            return ["status" => "error", "msg" => "Program bulunamadı"];
        }

        Gate::authorize(PermissionType::UPDATE->value, clone $schedule, "Programı yayınlama yetkiniz yok");

        return (new SchedulePublishService())->togglePublish($requestData['id']);
    }

    /**
     * Scope (Birim/Bölüm/Program) bazlı toplu program yayınlama/kaldırma işlemi
     *
     * @throws Exception
     */
    public function bulkPublishByScope(array $requestData): array
    {
        $user = AuthMiddleware::user();
        if (!$user || !Gate::hasAnyPermission($user, PermissionType::PUBLISH_SCHEDULE->value)) {
            throw new AuthorizationException("Ders programı yayınlama yetkiniz yok.");
        }

        $scope = $requestData['scope'] ?? null;
        $scopeId = isset($requestData['scope_id']) ? (int)$requestData['scope_id'] : null;
        
        if (!$scope || !$scopeId) {
            return ["status" => "error", "msg" => "Geçersiz birim/bölüm/program seçimi."];
        }

        $action = isset($requestData['action']) && $requestData['action'] === 'unpublish' ? false : true;

        $result = (new SchedulePublishService())->bulkPublishByScope(
            $scope,
            $scopeId,
            $requestData['semester'] ?? getSettingValue('semester'),
            $requestData['academic_year'] ?? getSettingValue('academic_year'),
            $requestData['type'] ?? '',
            $action,
            $requestData['owner_type_tab'] ?? null
        );

        $count = $result['count'];
        $notifiedCount = $result['notified_users'];
        $crossUnitCount = $result['cross_unit_notified'];
        
        $msg = $action ? "$count adet program başarıyla yayınlandı." : "$count adet program yayından kaldırıldı.";
        if ($action && $notifiedCount > 0) {
            $msg .= " ($notifiedCount hocaya e-posta gönderildi)";
        }
        if ($action && $crossUnitCount > 0) {
            $msg .= " (Farklı birimden $crossUnitCount hocaya bilgilendirme e-postası gönderildi)";
        }

        return [
            "status" => "success",
            "msg" => $msg
        ];
    }

    /**
     * @throws Exception
     */
    public function notifyScheduleChanges(array $requestData): array
    {
        Gate::authorizeRole('admin', false, "Değişiklik bildirimlerini gönderme yetkiniz yok");

        $notifiedCount = (new SchedulePublishService())->notifyChanges();
        
        if ($notifiedCount === 0) {
            return ["status" => "info", "msg" => "Bildirilecek değişiklik bulunamadı."];
        }

        return [
            "status" => "success",
            "msg" => "$notifiedCount hocaya bildirim e-postası gönderildi."
        ];
    }

    /**
     * Seçilen kapsam için yayın istatistiklerini getirir
     */
    public function getPublishStatusByScope(array $requestData): array
    {
        $user = AuthMiddleware::user();
        if (!$user || !Gate::hasAnyPermission($user, PermissionType::PUBLISH_SCHEDULE->value)) {
            throw new AuthorizationException("Ders programı durumunu görüntüleme yetkiniz yok.");
        }

        $scope = $requestData['scope'] ?? null;
        $scopeId = isset($requestData['scope_id']) ? (int)$requestData['scope_id'] : null;
        
        if (!$scope || !$scopeId) {
            return ["status" => "error", "msg" => "Geçersiz seçim."];
        }

        $stats = (new SchedulePublishService())->getPublishStatsByScope(
            $scope,
            $scopeId,
            $requestData['semester'] ?? getSettingValue('semester'),
            $requestData['academic_year'] ?? getSettingValue('academic_year'),
            $requestData['type'] ?? '',
            $requestData['owner_type_tab'] ?? null
        );

        return [
            "status" => "success",
            "all_published" => $stats['all_published'],
            "total_count" => $stats['total_count'],
            "published_count" => $stats['published_count'],
            "unpublished_count" => $stats['unpublished_count'],
            "details" => $stats['details'] ?? []
        ];
    }
}