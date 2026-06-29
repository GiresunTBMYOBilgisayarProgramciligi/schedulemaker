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
use App\Repositories\ScheduleRepository;
use App\Models\ScheduleItem;
use App\Helpers\ScheduleViewHelper;
use App\Core\Gate;

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
        if (in_array($dto->owner_type, ['user', 'classroom', 'lesson'])) {
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
        Gate::authorize('update', $schedule);

        $HTMLOut = "";

        if ($dto->semester_no !== null) {
            // birleştirilmiş dönem veya tek dönem
            $HTMLOut .= ScheduleViewHelper::prepareScheduleCard($dto, $only_table, $preference_mode, $no_card);
        } elseif (in_array($dto->owner_type, ['user', 'classroom', 'lesson'])) {
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

        $dtos = [];
        foreach ($items as $itemData) {
            $dtos[] = ScheduleItemData::fromArray($itemData);
        }

        if (count($dtos) > 0) {
            $schedule = clone (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', $schedule);
            }
        }

        try {
            $this->logger()->debug("Using LessonScheduleService::saveScheduleItems", $this->logContext());
            $service = new LessonScheduleService();
            $result = $service->saveScheduleItems($dtos);
            return $this->formatServiceResultToLegacy($result);
        } catch (\Throwable $e) {
            return [
                "status" => "error",
                "msg" => "Kayıt işlemi başarısız: " . $e->getMessage()
            ];
        }
    }

    /**
     * todo bu incelenip kaldırılmalı
     * Service result'ını eski formata çevirir (backward compatibility)
     */
    private function formatServiceResultToLegacy(SaveScheduleResult $result): array
    {
        return [
            [
                'id' => $result->createdIds
            ]
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

        $dtos = [];
        foreach ($items as $itemData) {
            $dtos[] = ScheduleItemData::fromArray($itemData);
        }

        if (count($dtos) > 0) {
            $schedule = clone (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', $schedule);
            }
        }

        try {
            $this->logger()->debug("Using LessonScheduleService::deleteScheduleItems", $this->logContext());
            $service = new LessonScheduleService();
            $result = $service->deleteScheduleItems($dtos);
            return $result->toArray();
        } catch (\Throwable $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            return [
                "status" => "error",
                "msg" => "Silme işlemi sırasında bir hata oluştu: " . $e->getMessage(),
            ];
        }
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

        $dtos = [];
        foreach ($items as $itemData) {
            $dtos[] = ScheduleItemData::fromArray($itemData);
        }

        if (count($dtos) > 0) {
            $schedule = clone (new ScheduleRepository())->find($dtos[0]->scheduleId);
            if ($schedule) {
                Gate::authorize('update', $schedule);
            }
        }

        try {
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
        } catch (\Throwable $e) {
            $this->logger()->error($e->getMessage(), ['exception' => $e]);
            $msg = $e->getMessage();
            $msgArray = explode("\n", $msg);
            return [
                "status" => "error",
                "msg" => count($msgArray) > 1 ? $msgArray : "Sistem Hatası: " . $msg,
            ];
        }
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
        try {
            $dto = (new ScheduleConflictFilterValidator())->getDTO($requestData, "checkScheduleCrash");
            $service = new ConflictService();
            $service->checkScheduleCrash($dto->toArray()); // Servis güncellendiğinde toArray kalkacak

            return ['status' => 'success'];
        } catch (\Throwable $e) {
            $this->logger()->error('checkScheduleCrash failed', ['exception' => (string) $e, 'payload' => $requestData]);
            $msg = $e->getMessage();
            $msgArray = explode("\n", $msg);
            return [
                "status" => "error",
                "msg" => count($msgArray) > 1 ? $msgArray : $msg,
            ];
        }
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
                Gate::authorize('update', $schedule);
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