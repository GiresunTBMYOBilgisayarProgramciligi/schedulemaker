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
use App\Core\Gate;

class ScheduleController extends Controller
{
    protected string $modelName = "App\Models\Schedule";

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * todo service işi
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır.
     * @param $type string  html | excel
     * @param int|null $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     * @throws Exception
     */
    private function generateEmptyWeek(?int $maxDayIndex = null): array
    {

        if ($maxDayIndex === null)
            throw new Exception("maxDayIndex belirtilmelidir");
        $emptyWeek = [];
        
        foreach (range(0, $maxDayIndex) as $index) {
            $emptyWeek["day{$index}"] = null;
        }
        return $emptyWeek;
    }

    /********************************
     * Görünüm ve Veri Hazırlama
     ********************************/

    /**
     * todo bu işlemlerin uzun uzun burada yapılması doğru değil service'e taşınmalı
     * Ders programı tablosunun verilerini oluşturur
     * Sadece tek bir tablo için veri oluşturur. Farklı dönem numaraları birleştirilecekse bu işlem sonradan yapılmalı.
     * @throws Exception
     * @return array
     */
    public function prepareScheduleRows(Schedule $schedule, $maxDayIndex = null): array
    {
        /*
         * Gün sayısı parametre ile belirlenebilir. Parametre verilmezse ayarlardan okunur.
         * Ders (lesson) için maxDayIndex, Sınav (exam) için maxDayIndex kullanılır.
         */
        if ($maxDayIndex === null) {
            $scheduleTypeStr = ExamType::isExamType($schedule->type) ? 'exam' : 'lesson';
            $maxDayIndex = getSettingValue('maxDayIndex', $scheduleTypeStr, 4);
        }

        /**
         * Boş tablo oluşturmak için tablo satır verileri
         */
        $scheduleRows = [];
        $weekCount = ($schedule->type === ExamType::FINAL->value) ? 2 : 1;

        for ($w = 0; $w < $weekCount; $w++) {
            $scheduleRows[$w] = [];
            if (ExamType::isExamType($schedule->type)) {
                $duration = getSettingValue('duration', 'exam', 30);
                $break = getSettingValue('break', 'exam', 0);
                // 08:00–17:00 arası 
                $start = new \DateTime('08:00');
                $end = new \DateTime('17:00');
                while ($start < $end) {
                    $slotStartTime = clone $start;
                    $slotEndTime = (clone $start)->modify("+$duration minutes");
                    $scheduleRows[$w][] = [
                        'slotStartTime' => $slotStartTime,
                        'slotEndTime' => $slotEndTime,
                        'days' => $this->generateEmptyWeek($maxDayIndex)
                    ];

                    $start = (clone $slotEndTime)->modify("+$break minutes");
                }
            } else {
                $duration = getSettingValue('duration', 'lesson', 50);
                $break = getSettingValue('break', 'lesson', 10);
                // 08:00–17:00 arası
                $start = new \DateTime('08:00');
                $end = new \DateTime('17:00');
                while ($start < $end) {
                    $slotStartTime = clone $start;
                    $slotEndTime = (clone $start)->modify("+$duration minutes");
                    $scheduleRows[$w][] = [
                        'slotStartTime' => $slotStartTime,
                        'slotEndTime' => $slotEndTime,
                        'days' => $this->generateEmptyWeek($maxDayIndex)
                    ];
                    $start = (clone $slotEndTime)->modify("+$break minutes"); // tenefüs arası
                }
            }
        }

        /*
         * Veri tabanından alınan bilgileri tablo satırları yerine yerleştiriliyor
         */
        foreach ($schedule->items as $scheduleItem) {
            // $this->logger()->debug("Schedule Item alındı", ['scheduleItem' => $scheduleItem]);
            $itemStart = \DateTime::createFromFormat('H:i:s', $scheduleItem->start_time) ?: \DateTime::createFromFormat('H:i', $scheduleItem->start_time);
            $itemEnd = \DateTime::createFromFormat('H:i:s', $scheduleItem->end_time) ?: \DateTime::createFromFormat('H:i', $scheduleItem->end_time);

            if (!$itemStart || !$itemEnd)
                continue;

            foreach ($scheduleRows[$scheduleItem->week_index] as &$row) {
                $slotStart = $row['slotStartTime'];

                if ($slotStart->format('H:i') >= $itemStart->format('H:i') && $slotStart->format('H:i') < $itemEnd->format('H:i')) {
                    $dayKey = 'day' . $scheduleItem->day_index;

                    if (array_key_exists($dayKey, $row['days'])) {
                        if ($row['days'][$dayKey] === null) {
                            $row['days'][$dayKey] = $scheduleItem;
                        } else {
                            // Çakışma durumu: preferred/unavailable olan item'ı yoksay, gerçek item'ı koru
                            $existing = $row['days'][$dayKey];

                            if (is_array($existing)) {
                                // Zaten array ise atla (savunma amaçlı)
                                continue;
                            }

                            if (in_array($scheduleItem->status, ['preferred', 'unavailable'])) {
                                // Yeni gelen preferred/unavailable ise, mevcut item'ı koru
                                continue;
                            } elseif (in_array($existing->status, ['preferred', 'unavailable'])) {
                                // Mevcut olan preferred/unavailable ise, yeni gerçek item'ı koy
                                $row['days'][$dayKey] = $scheduleItem;
                            } else {
                                // İkisi de gerçek item — array'e dönüştür (mevcut davranış, group vs.)
                                if (!is_array($row['days'][$dayKey])) {
                                    $row['days'][$dayKey] = [$row['days'][$dayKey]];
                                }
                                $row['days'][$dayKey][] = $scheduleItem;
                            }
                        }
                    }
                }
            }
        }

        //$this->logger()->debug('Schedule Rows oluşturuldu', ['scheduleRows' => $scheduleRows]);
        return $scheduleRows;
    }

    /**
     * todo servise taşınmalı
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    private function prepareScheduleCard(ScheduleFilterDTO $dto, bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        // Hoca, Derslik ve Ders programları dönemden bağımsızdır (Genel Program)
        if (in_array($dto->owner_type, ['user', 'classroom', 'lesson'])) {
            $data = $dto->toArray();
            $data['semester_no'] = null;
            $dto = ScheduleFilterDTO::fromArray($data);
        }

        $scheduleService = new ScheduleService();
        $schedule = $scheduleService->getOrCreateSchedule($dto);
        $availableLessons = ($only_table) ? [] : (new AvailabilityService())->availableLessons($schedule, $preference_mode);
        $scheduleRows = $this->prepareScheduleRows($schedule);

        $availableLessonsHTML = View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode,
            'owner_type' => $filters['owner_type'] ?? null
        ]);

        $createTableHeaders = function (int $weekIndex = 0) use ($dto): array {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = [];
            $isExam = ExamType::isExamType($dto->type);
            $type = $isExam ? 'exam' : 'lesson';

            $startDate = null;
            if ($isExam) {
                $examTypeEnum = ExamType::tryFrom($dto->type);
                if ($examTypeEnum) {
                    $settingKey = $examTypeEnum->startDateSettingKey();
                    if ($settingKey) {
                        $startDateString = getSettingValue($settingKey, 'exam');
                        if ($startDateString) {
                            $startDate = new \DateTime($startDateString);
                        }
                    }
                }
            }

            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
            for ($i = 0; $i <= $maxDayIndex; $i++) {
                $headerTitle = $days[$i];
                if ($startDate) {
                    $currentDate = (clone $startDate)->modify("+" . ($weekIndex * 7 + $i) . " days");
                    $headerTitle .= '<br><small>' . $currentDate->format('d.m.Y') . '</small>';
                }
                $headers[] = '<th>' . $headerTitle . '</th>';
            }
            return $headers;
        };

        // Her hafta için ayrı header'lar oluştur
        $allWeekHeaders = [];
        foreach ($scheduleRows as $weekIndex => $rows) {
            $allWeekHeaders[$weekIndex] = $createTableHeaders($weekIndex);
        }

        $isExam = ExamType::isExamType($schedule->type);
        $partialName = $isExam ? 'examScheduleTable' : 'lessonScheduleTable';

        $scheduleTableHTML = View::renderPartial('admin', 'schedules', $partialName, [
            'weekRows' => $scheduleRows,
            'weekHeaders' => $allWeekHeaders,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode
        ]);

        $ownerName = match ($dto->owner_type) {
            'user' => (new User())->find($dto->owner_id)->getFullName(),
            'program' => (new Program())->find($dto->owner_id)->name,
            'classroom' => (new Classroom())->find($dto->owner_id)->name,
            'lesson' => (new Lesson())->find($dto->owner_id)->getFullName(true),
            default => ""
        };

        //Semester No dizi ise dönemler birleştirilmiş demektir. Birleştirilmişse Başlık olarak Ders programı yazar
        $cardTitle = $dto->semester_no . " Yarıyıl Programı";
        $dataSemesterNo = 'data-semester-no="' . $dto->semester_no . '"';

        if (ExamType::isExamType($dto->type)) {
            $duration = getSettingValue('duration', 'exam', 30);
            $break = getSettingValue('break', 'exam', 0);
        } else {
            $duration = getSettingValue('duration', 'lesson', 50);
            $break = getSettingValue('break', 'lesson', 10);
        }

        return View::renderPartial('admin', 'schedules', 'scheduleCard', [
            'schedule' => $schedule,
            'availableLessonsHTML' => $availableLessonsHTML,
            'scheduleTableHTML' => $scheduleTableHTML,
            'ownerName' => $ownerName,
            'cardTitle' => $cardTitle,
            'dataSemesterNo' => $dataSemesterNo,
            'duration' => $duration,
            'break' => $break,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode,
            'weekCount' => count($scheduleRows),
            'no_card' => $no_card
        ]);
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
            $HTMLOut .= $this->prepareScheduleCard($dto, $only_table, $preference_mode, $no_card);
        } elseif (in_array($dto->owner_type, ['user', 'classroom', 'lesson'])) {
            // Hoca, Derslik ve Ders programları için tek bir genel program oluşturulur
            $HTMLOut .= $this->prepareScheduleCard($dto, $only_table, $preference_mode, $no_card);
        } else {
            $currentSemesters = getSemesterNumbers($dto->semester);
            foreach ($currentSemesters as $semester_no) {
                $data = $dto->toArray();
                $data['semester_no'] = $semester_no;
                $specificDto = ScheduleFilterDTO::fromArray($data);
                $HTMLOut .= $this->prepareScheduleCard($specificDto, $only_table, $preference_mode, $no_card);
            }
        }

        return $HTMLOut; //todo html string çıktısı controller görevi mi?
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