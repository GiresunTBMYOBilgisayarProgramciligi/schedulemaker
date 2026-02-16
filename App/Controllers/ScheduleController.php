<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\FeatureFlags;
use App\Core\View;
use App\Helpers\FilterValidator;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Services\ScheduleService;
use Exception;
use PDOException;
use function App\Helpers\find_key_starting_with;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedules';//todo artık tek tablo değil bu tablo adı nerelerde kullanılacak? 
    protected string $modelName = "App\Models\Schedule";

    public FilterValidator $validator;

    /********************************
     * Yardımcı ve Hazırlık Metodları
     ********************************/
    public function __construct()
    {
        parent::__construct();
        $this->validator = new FilterValidator();
    }
    /**
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır.
     * @param $type string  html | excel
     * @param int|null $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     * @throws Exception
     */
    private function generateEmptyWeek(string $type = 'html', ?int $maxDayIndex = null): array
    {

        if ($maxDayIndex === null)
            throw new Exception("maxDayIndex belirtilmelidir");
        $emptyWeek = [];
        foreach (range(0, $maxDayIndex) as $index) {
            $emptyWeek["day{$index}"] = null;
            if ($type == 'excel')
                $emptyWeek["classroom{$index}"] = null;
        }
        return $emptyWeek;
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek Buna artık gerek yok gibi
     * Başlangıç saatine ve ders saat miktarına göre saat dizisi oluşturur
     * @param string $startTimeRange Dersin ilk saat aralığı Örn. 08.00 - 08.50
     * @param int $hours
     * @return array
     */
    public function generateTimesArrayFromText(string $startTimeRange, int $hours, string $type = 'lesson'): array
    {
        $schedule = [];

        // Başlangıç ve bitiş saatlerini ayır
        [$start, $end] = explode("-", $startTimeRange);
        $currentTime = \DateTime::createFromFormat('H.i', trim($start));

        for ($i = 0; $i < $hours; $i++) {
            // Eğer ders ise ve saat 12 ise öğle arası için atla
            if ($type === 'lesson' && $currentTime->format('H') == '12') {
                $currentTime->modify('+1 hour');
            }

            $startFormatted = $currentTime->format('H.i');

            if (in_array($type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                $duration = getSettingValue('duration', 'exam', 30);
                $break = getSettingValue('break', 'exam', 0);
                $endTime = clone $currentTime;
                $endTime->modify("+$duration minutes");
                $endFormatted = $endTime->format('H.i');
                $schedule[] = "$startFormatted - $endFormatted";
                $currentTime = $endTime->modify("+$break minutes");
            } else {
                $duration = getSettingValue('duration', 'lesson', 50);
                $break = getSettingValue('break', 'lesson', 10);
                $endTime = clone $currentTime;
                $endTime->modify("+$duration minutes");
                $endFormatted = $endTime->format('H.i');
                $schedule[] = "$startFormatted - $endFormatted";
                $currentTime = $endTime->modify("+$break minutes");
            }
        }

        return $schedule;
    }

    public function lessonHourToMinute($scheduleType, $hours): int
    {
        if ($scheduleType === 'lesson') {
            $duration = getSettingValue('duration', 'lesson', 50);
            $break = getSettingValue('break', 'lesson', 10);
            return $hours * ($duration + $break);
        } elseif ($scheduleType === 'midterm-exam' || $scheduleType === 'final-exam' || $scheduleType === 'makeup-exam') {
            $duration = getSettingValue('duration', 'exam', 30);
            $break = getSettingValue('break', 'exam', 0);
            return $hours * ($duration + $break);
        }
        return 0;
    }

    /**
     * Hata mesajlarında göstermek için Item içindeki lesson_id'den ders adını bulur
     * @param ScheduleItem $item
     * @return string Ders Adı (Kodu) veya Bilinmeyen
     */
    private function getLessonNameFromItem(ScheduleItem $item): string
    {
        $slotDatas = $item->getSlotDatas();
        if (!empty($slotDatas)) {
            $names = [];
            foreach ($slotDatas as $sd) {
                if ($sd->lesson) {
                    $names[] = $sd->lesson->getFullName();
                }
            }
            return !empty($names) ? implode(", ", $names) : "Bilinmeyen Ders";
        }
        return "Bilinmeyen Öğe";
    }
    /********************************
     * Görünüm ve Veri Hazırlama
     ********************************/

    /**
     * Ders programı tamamlanmamış olan derslerin bilgilerini döner.
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableLessons(Schedule $schedule, bool $preferenceMode = false): array
    {
        if ($preferenceMode && in_array($schedule->owner_type, ['user', 'classroom', 'lesson'])) {
            // Sadece tercih modunda Preferred ve Unavailable kartlarını döndür
            return [
                (object) [
                    'id' => 'dummy-preferred',
                    'name' => '',
                    'code' => 'PREF',
                    'status' => 'preferred',
                    'hours' => 1,
                    'lecturer_id' => $schedule->owner_id, // Context hoca ise hoca ID'si
                    'is_dummy' => true
                ],
                (object) [
                    'id' => 'dummy-unavailable',
                    'name' => '',
                    'code' => 'UNAV',
                    'status' => 'unavailable',
                    'hours' => 1,
                    'lecturer_id' => $schedule->owner_id,
                    'is_dummy' => true
                ]
            ];
        }

        $available_lessons = [];

        $lessonFilters = [
            'semester' => $schedule->semester,
            'academic_year' => $schedule->academic_year,
            '!type' => 4// staj dersleri dahil değil
        ];

        if ($schedule->owner_type == "program") {
            $lessonFilters = array_merge($lessonFilters, [
                'program_id' => $schedule->owner_id,
            ]);
        } elseif ($schedule->owner_type == "classroom") {
            $classroom = (new Classroom())->find($schedule->owner_id);
            $lessonFilters = array_merge($lessonFilters, [
                'classroom_type' => $classroom->type,
            ]);
        } elseif ($schedule->owner_type == "user") {
            $lessonFilters = array_merge($lessonFilters, [
                'lecturer_id' => $schedule->owner_id,
            ]);
        } elseif ($schedule->owner_type == "lesson") {
            $lessonFilters = array_merge($lessonFilters, [
                'id' => $schedule->owner_id,
            ]);
        }

        // Eğer program schedule'ı ise semester_no filtresini ekle
        if ($schedule->semester_no !== null) {
            $lessonFilters['semester_no'] = $schedule->semester_no;
        }
        $lessonsList = (new Lesson())->get()->where($lessonFilters)->with(['lecturer', 'program'])->all();
        $this->logger()->debug("availableLessons found " . count($lessonsList) . " potential lessons for schedule " . $schedule->id);

        /**
         * Programa ait tüm derslerin program tamamlanma durumları kontrol ediliyor.
         * @var Lesson $lesson Model allmetodu sonucu oluşan sınıfı PHP strom tanımıyor. otomatik tamamlama olması için ekliyorum
         */
        foreach ($lessonsList as $lesson) {
            $isComplete = $lesson->IsScheduleComplete($schedule->type);
            if (!$isComplete) {
                //Ders Programı tamamlanmamışsa

                if ($schedule->type == 'lesson') {
                    $lesson->hours -= $lesson->placed_hours;// kalan saat dersin saati olarak güncelleniyor
                } elseif (in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                    $lesson->size = $lesson->remaining_size;// kalan mevcut dersin mevcudu  olarak güncelleniyor
                }

                $available_lessons[] = $lesson;
            }
        }

        return $available_lessons;
    }
    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmuş bir dizi döner
     * @param array $filters
     * @return array|bool
     * @throws Exception
     */
    public function createScheduleExcelTable(array $filters = []): array|bool
    {
        $filters = $this->validator->validate($filters, "createScheduleExcelTable");

        $schedules = (new Schedule())->get()->where($filters)->all();
        if (count($schedules) == 0)
            return false; // program boş ise false dön

        $scheduleRows = $this->prepareScheduleRows($schedules[0], 'excel');
        $scheduleArray = [];

        // Günler dinamik olarak oluşturuluyor
        $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
        $headerRow = ['']; // ilk hücre boş olacak (saat için)

        /*
         * Ders (lesson) için maxDayIndex, Sınav (exam) için maxDayIndex kullanılır.
         */
        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        $type = in_array($filters['type'], $examTypes) ? 'exam' : 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        //todo aşağıdaki kodlar geçersiz. iki haftalık düzen için yapay zekanın düzenlemesi. Ama o şekilde kullanılmayacak
        for ($i = 0; $i <= $maxDayIndex; $i++) {
            $headerRow[] = $days[$i % 7]; // Gün adı
            if (isset($filters['owner_type']) and $filters['owner_type'] != 'classroom')
                $headerRow[] = 'S';       // Sütun başlığı (Sınıf)
        }

        $scheduleArray[] = $headerRow;

        // Satırları doldur
        foreach ($scheduleRows as $time => $tableRow) {
            $row = [$time];
            foreach ($tableRow as $day) {
                $row[] = $day;
            }
            $scheduleArray[] = $row;
        }

        return $scheduleArray;
    }

    /**
     * Ders programı tablosunun verilerini oluşturur
     * Sadece tek bir tablo için veri oluşturur. Farklı dönem numaraları birleştirilecekse bu işlem sonradan yapılmalı.
     * @throws Exception
     * @return array
     */
    public function prepareScheduleRows(Schedule $schedule, $type = "html", $maxDayIndex = null): array
    {
        /*
         * Gün sayısı parametre ile belirlenebilir. Parametre verilmezse ayarlardan okunur.
         * Ders (lesson) için maxDayIndex, Sınav (exam) için maxDayIndex kullanılır.
         */
        if ($maxDayIndex === null) {
            $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
            $type = in_array($schedule->type, $examTypes) ? 'exam' : 'lesson';
            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        }

        /**
         * derslik tablosunda sınıf bilgisi gözükmemesi için type excel yerine html yapılıyor. excell türü sınıf sütünu ekliyor}
         */
        if ($schedule->owner_type == 'classroom') {
            $type = "html";
        }
        /**
         * Boş tablo oluşturmak için tablo satır verileri
         */
        $scheduleRows = [];
        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        $weekCount = ($schedule->type === 'final-exam') ? 2 : 1;

        for ($w = 0; $w < $weekCount; $w++) {
            $scheduleRows[$w] = [];
            if (in_array($schedule->type, $examTypes)) {
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
                        'days' => $this->generateEmptyWeek($type, $maxDayIndex)
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
                        'days' => $this->generateEmptyWeek($type, $maxDayIndex)
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
                            if (!is_array($row['days'][$dayKey])) {
                                $row['days'][$dayKey] = [$row['days'][$dayKey]];
                            }
                            $row['days'][$dayKey][] = $scheduleItem;
                        }
                    }
                }
            }
        }

        //$this->logger()->debug('Schedule Rows oluşturuldu', ['scheduleRows' => $scheduleRows]);
        return $scheduleRows;
    }

    /**
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    private function prepareScheduleCard($filters, bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        //$this->logger()->debug("Prepare Schedule Card için Filter alındı", ['filters' => $filters]);
        $filters = $this->validator->validate($filters, "prepareScheduleCard");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır (Genel Program)
        if (in_array($filters['owner_type'], ['user', 'classroom', 'lesson'])) {
            $filters['semester_no'] = null;
        }

        $schedule = (new Schedule())->firstOrCreate($filters);
        $availableLessons = ($only_table) ? [] : $this->availableLessons($schedule, $preference_mode);
        $scheduleRows = $this->prepareScheduleRows($schedule, "html");

        $availableLessonsHTML = View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode,
            'owner_type' => $filters['owner_type'] ?? null
        ]);

        $createTableHeaders = function (int $weekIndex = 0) use ($filters): array {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = [];
            $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
            $isExam = in_array($filters['type'], $examTypes);
            $type = $isExam ? 'exam' : 'lesson';

            $startDate = null;
            if ($isExam) {
                $settingKey = match ($filters['type']) {
                    'midterm-exam' => 'midterm_start_date',
                    'final-exam' => 'final_start_date',
                    'makeup-exam' => 'makeup_start_date',
                    default => null
                };
                if ($settingKey) {
                    $startDateString = getSettingValue($settingKey, 'exam');
                    if ($startDateString) {
                        $startDate = new \DateTime($startDateString);
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

        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        $isExam = in_array($schedule->type, $examTypes);
        $partialName = $isExam ? 'examScheduleTable' : 'lessonScheduleTable';

        $scheduleTableHTML = View::renderPartial('admin', 'schedules', $partialName, [
            'weekRows' => $scheduleRows,
            'weekHeaders' => $allWeekHeaders,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode
        ]);

        $ownerName = match ($filters['owner_type']) {
            'user' => (new User())->find($filters['owner_id'])->getFullName(),
            'program' => (new Program())->find($filters['owner_id'])->name,
            'classroom' => (new Classroom())->find($filters['owner_id'])->name,
            'lesson' => (new Lesson())->find($filters['owner_id'])->getFullName(),
            default => ""
        };

        //Semester No dizi ise dönemler birleştirilmiş demektir. Birleştirilmişse Başlık olarak Ders programı yazar
        $cardTitle = $filters['semester_no'] . " Yarıyıl Programı";
        $dataSemesterNo = 'data-semester-no="' . $filters['semester_no'] . '"';

        if (in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam'])) {
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
     * @param array $filters
     * @param bool $preference_mode
     * @return string
     * @throws Exception
     */
    public function getAvailableLessonsHTML(array $filters = [], bool $preference_mode = false): string
    {
        $filters = $this->validator->validate($filters, "prepareScheduleCard");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır
        if (in_array($filters['owner_type'] ?? '', ['user', 'classroom', 'lesson'])) {
            $filters['semester_no'] = null;
        }

        $availableLessons = [];
        $schedule = null;

        $schedule = (new Schedule())->firstOrCreate($filters);
        $availableLessons = $this->availableLessons($schedule, $preference_mode);

        return View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => false,
            'preference_mode' => $preference_mode,
            'owner_type' => $filters['owner_type'] ?? null
        ]);
    }

    /**
     * Dönem numarasına göre birleştirilmiş yada her bir dönem için Schedule Card oluşturur
     * @param array $filters
     * @param bool $only_table
     * @return string
     * @throws Exception
     */
    public function getSchedulesHTML(array $filters = [], bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        $filters = $this->validator->validate($filters, "getSchedulesHTML");
        $HTMLOut = "";

        if (key_exists("semester_no", $filters)) {
            // birleştirilmiş dönem
            $HTMLOut .= $this->prepareScheduleCard($filters, $only_table, $preference_mode, $no_card);
        } elseif (in_array($filters['owner_type'], ['user', 'classroom', 'lesson'])) {
            // Hoca, Derslik ve Ders programları için tek bir genel program oluşturulur
            $filters['semester_no'] = null;
            $HTMLOut .= $this->prepareScheduleCard($filters, $only_table, $preference_mode, $no_card);
        } else {
            $currentSemesters = getSemesterNumbers($filters["semester"]);
            foreach ($currentSemesters as $semester_no) {
                $filters['semester_no'] = $semester_no;
                $HTMLOut .= $this->prepareScheduleCard($filters, $only_table, $preference_mode, $no_card);
            }
        }

        return $HTMLOut;
    }

    /********************************
     * KAYIT VE GÜNCELLEME İŞLEMLERİ
     ********************************/
    /**
     * Sınav Itemlerini kaydeder ve kardeş programlara (Hoca, Derslik, Program, Ders) yansıtır.
     * Sınavlara özel veri süzme ve referans mantığı içerir.
     * @param array $itemsData
     * @return array
     * @throws Exception
     */
    public function saveExamScheduleItems(array $itemsData): array
    {
        $service = new \App\Services\ScheduleService();
        $result = $service->saveExamScheduleItems($itemsData);

        return $this->formatServiceResultToLegacy($result);
    }

    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     * 
     * Feature Flag: use_new_schedule_service = '1' ise yeni ScheduleService kullanılır
     * 
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return array
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): array
    {
        $service = new ScheduleService();
        $result = $service->saveScheduleItems($itemsData);

        // Return formatını eski AjaxRouter sistemiyle uyumlu hale getir (nested array)
        return [
            [
                'id' => $result->createdIds
            ]
        ];
    }

    /**
     * Service result'ını eski formata çevirir (backward compatibility)
     * 
     * Eski format: [{owner_type: [id1, id2, ...]}, ...]
     * Yeni format: [id1, id2, ...]
     * 
     * AjaxRouter.php:642-644 nested foreach bekliyor
     * 
     * @param \App\DTOs\SaveScheduleResult $result
     * @return array
     */
    private function formatServiceResultToLegacy(\App\DTOs\SaveScheduleResult $result): array
    {
        // Eski sistem nested array bekliyor: [{'id': [1,2,3]}]
        // v1.0 sadece tek schedule'a kayıt yapıyor, gerçek formatı döndüremiyoruz
        // Geçici çözüm: id'leri 'id' anahtarı altında topla
        return [
            [
                'id' => $result->createdIds
            ]
        ];
    }



    /*********************
     *  Çakışma VE UYGUNLUK KONTROL İŞLEMLERİ
     **********************/

    /**
     * Programa eklenmek isteyen itemler için çakışma kontrolü yapar
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        $filters = $this->validator->validate($filters, "checkScheduleCrash");
        //$this->logger()->debug("Check Schedule Crash Filters: ", $this->logContext($filters));

        $items = json_decode($filters['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Geçersiz JSON verisi");
        }

        $errors = [];
        foreach ($items as $itemData) {
            $this->checkItemConflict($itemData, $errors);
        }

        if (!empty($errors)) {
            $errors = array_unique($errors);
            throw new Exception(implode("\n", $errors));
        }

        return true;
    }

    /**
     * checkItemConflict - ConflictResolver kullanarak çakışma kontrolü yapar
     * TODO: Bu metod ScheduleService'e taşınacak
     */
    private function checkItemConflict(array $itemData, array &$errors = []): void
    {
        // Data parse + validation
        $data = $itemData['data'];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new Exception("Geçersiz data formatı - array of objects bekleniyor");
        }

        $lessonId = $data[0]['lesson_id'] ?? null;
        $lecturerId = $data[0]['lecturer_id'] ?? null;
        $classroomId = $data[0]['classroom_id'] ?? null;

        if (!$lessonId) {
            throw new Exception("lesson_id bulunamadı");
        }

        $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
        if (!$lesson) {
            throw new Exception("Ders bulunamadı");
        }

        // Owner'ları belirle
        $owners = $this->determineOwners($itemData, $lesson, $lecturerId, $classroomId);

        // Target schedule'ı bul
        $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
        if (!$targetSchedule) {
            throw new Exception("Hedef Program bulunamadı");
        }

        // ConflictResolver kullanarak conflict check
        $conflictResolver = new \App\Services\Helpers\ConflictResolver();
        $conflictErrors = $conflictResolver->checkConflicts($itemData, $owners, $targetSchedule, $lesson);

        $errors = array_merge($errors, $conflictErrors);
    }

    /**
     * Item için owner'ları belirler (user, classroom, program, lesson)
     */
    private function determineOwners(array $itemData, Lesson $lesson, ?int $lecturerId, ?int $classroomId): array
    {
        $owners = [];
        $examAssignments = $itemData['detail']['assignments'] ?? null;

        if ($examAssignments) {
            // Sınav (Çoklu Atama)
            $owners[] = ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no];
            $owners[] = ['type' => 'lesson', 'id' => $lesson->id];
            foreach ($examAssignments as $assignment) {
                $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
                $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
            }
        } else {
            // Normal Ders
            $owners = [
                ['type' => 'user', 'id' => $lecturerId],
                ['type' => 'classroom', 'id' => ($lesson->classroom_type == 3) ? null : $classroomId],
                ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
                ['type' => 'lesson', 'id' => $lesson->id]
            ];
        }

        // Child Lessons için de owner ekle
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                $owners[] = ['type' => 'lesson', 'id' => $childLesson->id];
                if ($childLesson->program_id) {
                    $owners[] = ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no];
                }
            }
        }

        return $owners;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder
     * Mantık: (Start1 < End2) && (Start2 < End1)
     * @param string $start1 Birinci aralık başlangıç (H:i)
     * @param string $end1 Birinci aralık bitiş (H:i)
     * @param string $start2 İkinci aralık başlangıç (H:i)
     * @param string $end2 İkinci aralık bitiş (H:i)
     * @return bool Çakışma varsa true döner
     */
    private function checkOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        // Zamanları H:i formatına normalize et
        $start1 = substr($start1, 0, 5);
        $end1 = substr($end1, 0, 5);
        $start2 = substr($start2, 0, 5);
        $end2 = substr($end2, 0, 5);

        return ($start1 < $end2) && ($start2 < $end1);
    }

    /**
     * Zaman çakışması tespit edildiğinde, Items status durumuna göre bunun bir hata olup olmadığına karar verir.
     * Status: unavailable ve single -> Hata
     * Status: group -> Grup kurallarına uyuyorsa Hata Yok, uymuyorsa Hata
     * Status: preferred -> Hata Yok
     * @param array $newItemData Yeni eklenecek item verisi
     * @param ScheduleItem $existingItem Mevcut çakışan item
     * @param Lesson $newLesson Yeni eklenen ders
     * @param Schedule $currentSchedule Çakışmanın yaşandığı program (Hata mesajında isim göstermek için)
     * @return string|null Çakışma kuralı ihlal edilirse hata mesajı döner, yoksa null
     */
    private function resolveConflict(array $newItemData, ScheduleItem $existingItem, Lesson $newLesson, Schedule $currentSchedule): ?string
    {
        // Kendi kendisiyle çakışıyorsa (update durumu vs) yoksay
        if (isset($newItemData['id']) && $newItemData['id'] == $existingItem->id) {
            return null;
        }

        $crashInfo = "{$currentSchedule->getScheduleScreenName()} ({$existingItem->start_time} - {$existingItem->end_time})";

        // Status Kontrolü
        switch ($existingItem->status) {
            case 'unavailable':
                return "{$crashInfo}: Bu saat aralığı uygun değil.";
            case 'single':
                // Single ders varsa üzerine ders eklenemez
                return "{$crashInfo}: Bu saatte zaten bir ders mevcut: " . $this->getLessonNameFromItem($existingItem);
            case 'group':
                // Grup mantığı
                // Yeni ders aynı zamanda grup dersi olmalı (Lesson group_no > 0)
                if ($newLesson->group_no < 1) {
                    return "{$crashInfo}: Grup dersi üzerine normal ders eklenemez.";
                }

                // Mevcut gruptaki dersleri kontrol et
                $slotDatas = $existingItem->getSlotDatas();
                foreach ($slotDatas as $sd) {
                    if (!$sd->lesson)
                        continue;

                    // Dersler farklı olmalı
                    if ($sd->lesson->id == $newLesson->id) {
                        return "{$crashInfo}: Aynı ders aynı saatte tekrar eklenemez (Grup olsa bile).";
                    }

                    // Hoca aynı olmamalı
                    // ESKİ SİSTEM FORMAT: data = [{"lesson_id": "503", "lecturer_id": "158", ...}]
                    $newLecturerId = $newItemData['data'][0]['lecturer_id'] ?? null;
                    if ($sd->lecturer && $newLecturerId && $sd->lecturer->id == $newLecturerId) {
                        return "{$crashInfo}: Hoca aynı anda iki farklı derse giremez: " . $sd->lecturer->getFullName();
                    }

                    // Grup numaraları farklı olmalı
                    if ($sd->lesson->group_no == $newLesson->group_no) {
                        return "{$crashInfo}: Aynı grup numarasına sahip dersler çakışamaz.";
                    }
                }

                // Buraya geldiyse uygundur (Farklı ders, farklı grup, ikisi de grup)
                break;
            case 'preferred':
                // Tercih edilen saat, çakışma yok
                break;
            default:
                return "{$crashInfo}: Bilinmeyen durum: " . $existingItem->status;
        }
        return null;
    }
    /**
     * Belirtilen filtrelere uygun dersliklerin listesini döndürür
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableClassrooms(array $filters = []): array
    {
        $filters = $this->validator->validate($filters, "availableClassrooms");
        $schedule = (new Schedule())->where(["id" => $filters['schedule_id']])->with("items")->first() ?: throw new Exception("Uygun derslikleri berlirlemek için Program bulunamadı");

        /**
         * dersin derslik türü ile aynı türdeki bütün derslikler scheduleları ile birlikte çağırılacak. ama schedule'ları henüz oluşturulmamış olabileceğinden bütün derslikler alınacak
         * ve owner_type classroom olan ve diğer bilgileri gelen schedule ile aynı olan schedule'ler firstOrCreate ile çağırılacak
         * filters['startTime'] ve filters['hours'] bilgisine göre endTime hesaplanacak
         * bütün dersliklerin schedule itemleri arasında belirtilen bağlangıç saati ve hesaplanan bitiş saati arasında bir schedule item varsa o derslik uygun olmayacaktır.
         * bu schedule'lerin 
         */
        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Derslik türünü belirlemek için ders bulunamadı");

        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        if (in_array($schedule->type, $examTypes)) {
            // Sınav programı ise UZEM (3) hariç tüm derslikler
            $classrooms = (new Classroom())->get()->where(["type" => ['!=' => 3]])->all();
        } else {
            // Ders programı ise derslik türü filtrelemesi (Karma ise Lab(2) ve Derslik(1) dahil)
            $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
            $classrooms = (new Classroom())->get()->where(["type" => ['in' => $classroom_type]])->all();
        }

        $availableClassrooms = [];
        $itemsToCheck = json_decode($filters['items'], true) ?: [];

        foreach ($classrooms as $classroom) {
            $classroomSchedule = (new Schedule())->firstOrCreate([
                'type' => $schedule->type,
                'owner_type' => 'classroom',
                'owner_id' => $classroom->id,
                'semester_no' => null, // Derslik programları dönemden bağımsızdır
                'semester' => $schedule->semester,
                'academic_year' => $schedule->academic_year
            ]);

            $existingItems = (new ScheduleItem())->get()->where([
                'schedule_id' => $classroomSchedule->id,
                'day_index' => $filters['day_index'],
                'week_index' => $filters['week_index']
            ])->all();

            $isAvailable = true;
            // UZEM (3) tipi sınıflar her zaman uygun sayılır
            if ($classroom->type != 3) {
                foreach ($itemsToCheck as $checkItem) {
                    foreach ($existingItems as $existingItem) {
                        if ($this->checkOverlap($checkItem['start_time'], $checkItem['end_time'], $existingItem->start_time, $existingItem->end_time)) {
                            $isAvailable = false;
                            break 2;
                        }
                    }
                }
            }

            if ($isAvailable) {
                $availableClassrooms[] = $classroom;
            }
        }
        return $availableClassrooms;
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * Belirtilen filtrelere uygun gözetmenlerin listesini döndürür
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableObservers(array $filters = []): array
    {
        $filters = $this->validator->validate($filters, "availableObservers");

        $observerFilters = ['role' => ['in' => ['lecturer', 'department_head', 'manager', 'submanager']]];
        $observers = (new UserController())->getListByFilters($observerFilters);

        $availableObservers = [];
        $itemsToCheck = json_decode($filters['items'], true) ?: [];

        foreach ($observers as $observer) {
            $userSchedule = (new Schedule())->firstOrCreate([
                'type' => $filters['type'],
                'owner_type' => 'user',
                'owner_id' => $observer->id,
                'semester_no' => null, // Kullanıcı programları dönemden bağımsızdır
                'semester' => $filters['semester'],
                'academic_year' => $filters['academic_year']
            ]);

            $existingItems = (new ScheduleItem())->get()->where([
                'schedule_id' => $userSchedule->id,
                'day_index' => $filters['day_index'],
                'week_index' => $filters['week_index']
            ])->all();

            $isAvailable = true;
            foreach ($itemsToCheck as $checkItem) {
                foreach ($existingItems as $existingItem) {
                    if ($this->checkOverlap($checkItem['start_time'], $checkItem['end_time'], $existingItem->start_time, $existingItem->end_time)) {
                        $isAvailable = false;
                        break 2;
                    }
                }
            }

            if ($isAvailable) {
                $availableObservers[] = $observer;
            }
        }

        return $availableObservers;
    }

    public function wipeResourceSchedules(string $ownerType, int $ownerId): void
    {
        $this->logger()->debug("wipeResourceSchedules START for $ownerType ID: $ownerId");

        // 1. Varlığın kendi ana programlarını ve bu programlara ait tüm itemları sil
        $schedules = (new Schedule())->get()->where(['owner_type' => $ownerType, 'owner_id' => $ownerId])->all();
        $this->logger()->debug("wipeResourceSchedules schedules count: " . count($schedules));
        $this->logger()->debug("wipeResourceSchedules schedules: " . json_encode($schedules));
        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $schedule->id])->all();
            $this->logger()->debug("wipeResourceSchedules items count: " . count($items));
            $this->logger()->debug("wipeResourceSchedules items: " . json_encode($items));
            foreach ($items as $item) {
                // deleteScheduleItems metodu sibling'leri de bulup silecektir.
                $this->deleteScheduleItems([$item->getArray()], false);
            }
            $schedule->delete();
        }

        $this->logger()->debug("wipeResourceSchedules COMPLETED for $ownerType ID: $ownerId");
    }

    public function deleteScheduleItems(array $items, bool $expandGroup = true): array
    {
        // Log için silinmeden önce tüm ders adlarını topla
        $lessonNames = [];
        if (!empty($items)) {
            foreach ($items as $item) {
                if (isset($item['id'])) {
                    $si = (new ScheduleItem())->where(['id' => (int) $item['id']])->first();
                    if ($si) {
                        $name = $this->getLessonNameFromItem($si);
                        if ($name && $name !== "Bilinmeyen Ders" && !in_array($name, $lessonNames)) {
                            $lessonNames[] = $name;
                        }
                    }
                }
            }
        }
        $lessonName = !empty($lessonNames) ? implode(", ", $lessonNames) : "Bilinmeyen Ders";

        /**
         * silinen yada güncellenen ScheduleItem id'leri
         * @var mixed
         */
        $deletedIds = [];
        $errors = [];

        $ignoreSiblings = filter_var($this->data['ignore_siblings'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 1. Kardeş Öğeleri (Siblings) Batch Halinde İşle
        $createdItems = [];
        $processedSiblingIds = [];

        $isInitiator = !$this->database->inTransaction();
        if ($isInitiator) {
            $this->database->beginTransaction();
        }

        try {
            foreach ($items as $itemData) {
                $id = (int) ($itemData['id'] ?? 0);
                if (!$id)
                    continue;

                if (in_array($id, $processedSiblingIds)) {
                    continue;
                }

                // Ana öğeyi ve program tipini bul
                $scheduleItem = (new ScheduleItem())->where(['id' => $id])->with('schedule')->first();
                if (!$scheduleItem) {
                    continue;
                }

                $type = 'lesson';
                if ($scheduleItem->schedule && in_array($scheduleItem->schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                    $type = 'exam';
                }
                $duration = getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
                $break = getSettingValue('break', $type, $type === 'exam' ? 0 : 10);

                // 2. Kardeşleri (Hoca, Derslik, Program kopyaları) bul
                if ($ignoreSiblings) {
                    $siblings = [$scheduleItem];
                } else {
                    if ($type === 'exam') {
                        $siblings = $this->findExamSiblingItems($scheduleItem);
                    } else {
                        $baseLessonIds = [];
                        foreach ($scheduleItem->getSlotDatas() as $sd) {
                            if ($sd->lesson) {
                                $baseLessonIds[] = (int) $sd->lesson->id;
                            }
                        }
                        $siblings = $this->findSiblingItems($scheduleItem, $baseLessonIds);
                    }
                }

                $siblingIds = array_map(fn($s) => (int) $s->id, $siblings);

                // 3. Bu kardeş grubu için İSTEKTEKİ (BULK) TÜM silme aralıklarını ve DERS ID'lerini topla
                $rawIntervals = [];
                $targetLessonIds = [];
                foreach ($items as $reqItem) {
                    if (in_array((int) $reqItem['id'], $siblingIds)) {
                        $rawIntervals[] = [
                            'start' => substr($reqItem['start_time'], 0, 5),
                            'end' => substr($reqItem['end_time'], 0, 5)
                        ];

                        // İstekteki item'ın data dizisindeki tüm dersleri hedef listeye ekle
                        if (!empty($reqItem['data'])) {
                            foreach ($reqItem['data'] as $d) {
                                if (isset($d['lesson_id'])) {
                                    $lId = (int) $d['lesson_id'];
                                    if (!in_array($lId, $targetLessonIds)) {
                                        $targetLessonIds[] = $lId;

                                        if ($expandGroup) {
                                            // Tüm grubu dahil et (Grup = Parent + Tüm Çocuklar)
                                            $lObj = (new Lesson())->where(['id' => $lId])->with(['childLessons', 'parentLesson'])->first();
                                            if ($lObj) {
                                                if ($lObj->parent_lesson_id) {
                                                    if (!in_array((int) $lObj->parent_lesson_id, $targetLessonIds))
                                                        $targetLessonIds[] = (int) $lObj->parent_lesson_id;

                                                    // Parent'ın diğer çocuklarını da bulmak için parent'ı yükle
                                                    $parentObj = (new Lesson())->where(['id' => $lObj->parent_lesson_id])->with(['childLessons'])->first();
                                                    if ($parentObj) {
                                                        foreach ($parentObj->childLessons as $cl) {
                                                            if (!in_array((int) $cl->id, $targetLessonIds))
                                                                $targetLessonIds[] = (int) $cl->id;
                                                        }
                                                    }
                                                } elseif (!empty($lObj->childLessons)) {
                                                    foreach ($lObj->childLessons as $cl) {
                                                        if (!in_array((int) $cl->id, $targetLessonIds))
                                                            $targetLessonIds[] = (int) $cl->id;
                                                    }
                                    }
                                }
                            }
                        }

                // Aralıkları sırala ve birleştir (bitişik dersler tek interval olsun)
                usort($rawIntervals, fn($a, $b) => strcmp($a['start'], $b['start']));
                $mergedIntervals = [];
                foreach ($rawIntervals as $interval) {
                    if (empty($mergedIntervals)) {
                        $mergedIntervals[] = $interval;
                    } else {
                        $lastIdx = count($mergedIntervals) - 1;
                        $lastEnd = $mergedIntervals[$lastIdx]['end'];
                        $gapMinutes = (strtotime($interval['start']) - strtotime($lastEnd)) / 60;

                        if ($gapMinutes >= 0 && $gapMinutes <= $break) {
                            $mergedIntervals[$lastIdx]['end'] = max($mergedIntervals[$lastIdx]['end'], $interval['end']);
                        } else {
                            $mergedIntervals[] = $interval;
                        }
                    }
                }

                if (empty($mergedIntervals))
                    continue;

                // 4. Her bir kardeşe silme/parçalama işlemini uygula
                // Önemli: Önce tüm kardeşleri siliyoruz ki oluşturulacak parçalarla çakışmasın (Duplicate Entry önlemi)
                foreach ($siblings as $sibling) {
                    $sibling->delete();
                }

                foreach ($siblings as $sibling) {
                    // processItemDeletion artık silme işlemini yapmayacak (yukarıda yaptık)
                    $result = $this->processItemDeletion($sibling, $mergedIntervals, $targetLessonIds, $duration, $break, false);

                    if ($result['deleted']) {
                        $deletedIds[] = $sibling->id;
                    }
                    if (!empty($result['created'])) {
                        $createdItems = array_merge($createdItems, $result['created']);
                    }
                }

                $processedSiblingIds = array_unique(array_merge($processedSiblingIds, $siblingIds));
            }
            if ($isInitiator) {
                $this->database->commit();
            }

            // Log the action
            $user = (new UserController())->getCurrentUser();
            $username = $user ? $user->getFullName() : "Sistem";

            $scheduleId = $items[0]['schedule_id'] ?? null;
            if (!$scheduleId && !empty($deletedIds)) {
                $firstDeleted = (new ScheduleItem())->where(['id' => $deletedIds[0]])->first();
                $scheduleId = $firstDeleted ? $firstDeleted->schedule_id : null;
            }

            $schedule = $scheduleId ? (new Schedule())->find($scheduleId) : null;
            $screenName = $schedule ? $schedule->getScheduleScreenName() : "";
            $typeLabel = $schedule ? $schedule->getScheduleTypeName() : "program";

            $this->logger()->info("$username $typeLabel programını düzenledi: Silindi. Program: $screenName, Ders: $lessonName", $this->logContext());

        } catch (\Exception $e) {
            if ($isInitiator) {
                $this->database->rollBack();
            }
            $this->logger()->error("Silme işlemi başarısız: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }

        return [
            'status' => empty($errors) ? 'success' : 'warning',
            'deletedIds' => array_unique($deletedIds),
            'createdItems' => $createdItems,
            'errors' => $errors
        ];
    }

    /**
     * Verilen item ile ilişkili diğer programlardaki (Hoca, Sınıf vb.) kopyaları bulur.
     */
    public function findSiblingItems(ScheduleItem $baseItem, array $lessonIds): array
    {
        $siblingsKeyed = [$baseItem->id => $baseItem];

        $baseSchedule = (new Schedule())->find($baseItem->schedule_id);
        if (!$baseSchedule)
            return array_values($siblingsKeyed);

        // 1. Etkilenen derslere (atanmış hoca, sınıf ve programlarına) göre owner listesini oluştur
        $ownerList = [];

        foreach ($baseItem->getSlotDatas() as $slotData) {
            $lesson = $slotData->lesson;
            if (!$lesson)
                continue;

            // Sadece silinmek istenen dersler arasındaysa bu atamanın sahiplerini bul
            if (in_array((int) $lesson->id, $lessonIds)) {
                // Grubu tamamla (Eğer çocuksa parent'ı, eğer parentsa çocukları ekle)
                $relatedLessons = [];
                if ($lesson->parent_lesson_id) {
                    $parent = (new Lesson())->where(['id' => $lesson->parent_lesson_id])->with(['childLessons'])->first();
                    if ($parent) {
                        $relatedLessons[] = $parent;
                        foreach ($parent->childLessons as $cl)
                            $relatedLessons[] = $cl;
                    }
                } elseif (!empty($lesson->childLessons)) {
                    $relatedLessons[] = $lesson;
                    foreach ($lesson->childLessons as $cl)
                        $relatedLessons[] = $cl;
                } else {
                    $relatedLessons[] = $lesson;
                }

                foreach ($relatedLessons as $rLesson) {
                    // Lesson owner
                    $ownerList[] = ['type' => 'lesson', 'id' => (int) $rLesson->id, 'semester_no' => null];

                    // Program owner
                    if ($rLesson->program_id) {
                        $ownerList[] = ['type' => 'program', 'id' => (int) $rLesson->program_id, 'semester_no' => $rLesson->semester_no];
                    }

                    // Shared owners (Lecturer/Classroom) - Sadece ilk (veya ana) dersten almak yeterli ama her biri için bakmak daha güvenli
                    // Not: getSlotDatas() zaten baseItem'dan geldiği için lecturer/classroom orada sabit.
                }

                // Lecturer (User) owner
                if ($slotData->lecturer) {
                    $ownerList[] = ['type' => 'user', 'id' => (int) $slotData->lecturer->id, 'semester_no' => null];
                }

                // Classroom owner
                if ($slotData->classroom) {
                    $ownerList[] = ['type' => 'classroom', 'id' => (int) $slotData->classroom->id, 'semester_no' => null];
                }
            }
        }

        // 2. Owner listesini unique hale getir
        $uniqueOwners = [];
        foreach ($ownerList as $owner) {
            $ownerType = $owner['type'];
            $ownerId = $owner['id'];
            // saveScheduleItems ile uyumlu semester_no mantığı
            $sNo = ($ownerType === 'program') ? $owner['semester_no'] : null;

            $key = $ownerType . "_" . $ownerId . "_" . ($sNo ?? 'null');
            $uniqueOwners[$key] = [
                'type' => $ownerType,
                'id' => $ownerId,
                'semester_no' => $sNo
            ];
        }

        // 3. Her bir owner için ilgili schedule'ı bul ve içindeki çakışan item'ları topla
        foreach ($uniqueOwners as $owner) {
            $scheduleFilters = [
                'semester' => $baseSchedule->semester,
                'academic_year' => $baseSchedule->academic_year,
                'type' => $baseSchedule->type,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id'],
                'semester_no' => $owner['semester_no']
            ];

            $schedules = (new Schedule())->get()->where($scheduleFilters)->all();

            foreach ($schedules as $schedule) {
                // İlgili schedule ve gün için itemları getir
                $items = (new ScheduleItem())->get()->where([
                    'schedule_id' => $schedule->id,
                    'day_index' => $baseItem->day_index,
                    'week_index' => $baseItem->week_index
                ])->all();

                foreach ($items as $item) {
                    // Zaman çakışması kontrolü: Sadece baseItem ile çakışanlar siblingdir.
                    // Bu sayede farklı saatlerdeki bloklar birbirini "processed" diyerek engellemez.
                    if ($this->checkOverlap($baseItem->start_time, $baseItem->end_time, $item->start_time, $item->end_time)) {
                        if (!isset($siblingsKeyed[$item->id])) {
                            $siblingsKeyed[$item->id] = $item;
                        }
                    }
                }
            }
        }
        return array_values($siblingsKeyed);
    }

    /**
     * Sınav kayıtları için ilişkili diğer programlardaki kopyaları bulur.
     */
    public function findExamSiblingItems(ScheduleItem $baseItem): array
    {
        $siblingsKeyed = [$baseItem->id => $baseItem];
        $baseSchedule = (new Schedule())->find($baseItem->schedule_id);
        if (!$baseSchedule)
            return array_values($siblingsKeyed);

        $detail = is_string($baseItem->detail) ? json_decode($baseItem->detail, true) : $baseItem->detail;
        $ownerList = [];

        if (in_array($baseSchedule->owner_type, ['program', 'lesson'])) {
            // Program/Ders'ten geliyorsa, detail['assignments'] içindeki tüm derslik ve gözetmenleri ekle
            if (isset($detail['assignments']) && is_array($detail['assignments'])) {
                foreach ($detail['assignments'] as $asgn) {
                    $ownerList[] = ['type' => 'user', 'id' => (int) $asgn['observer_id'], 'semester_no' => null];
                    $ownerList[] = ['type' => 'classroom', 'id' => (int) $asgn['classroom_id'], 'semester_no' => null];
                }
            }
            // Ayrıca bağlı tüm dersleri ve onların programlarını ekle
            $lessonId = $baseItem->data[0]['lesson_id'] ?? null;
            if ($lessonId) {
                $lesson = (new Lesson())->find($lessonId);
                if ($lesson) {
                    $linkedIds = $lesson->getLinkedLessonIds();
                    foreach ($linkedIds as $lId) {
                        $ownerList[] = ['type' => 'lesson', 'id' => (int) $lId, 'semester_no' => null];
                        $lObj = (new Lesson())->find($lId);
                        if ($lObj && $lObj->program_id) {
                            $ownerList[] = ['type' => 'program', 'id' => (int) $lObj->program_id, 'semester_no' => $lObj->semester_no];
                        }
                    }
                }
            }
        } else {
            // User/Classroom'dan geliyorsa, program_item_id üzerinden ana program öğesini bul
            $programItemId = $detail['program_item_id'] ?? null;
            if ($programItemId) {
                $programItem = (new ScheduleItem())->where(['id' => $programItemId])->with('schedule')->first();
                if ($programItem) {
                    return $this->findExamSiblingItems($programItem);
                }
            }
        }

        // Unique Owners
        $uniqueOwners = [];
        foreach ($ownerList as $owner) {
            $key = $owner['type'] . "_" . $owner['id'] . "_" . ($owner['semester_no'] ?? 'null');
            $uniqueOwners[$key] = $owner;
        }

        foreach ($uniqueOwners as $owner) {
            $scheduleFilters = [
                'semester' => $baseSchedule->semester,
                'academic_year' => $baseSchedule->academic_year,
                'type' => $baseSchedule->type,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id'],
                'semester_no' => $owner['semester_no']
            ];

            $schedules = (new Schedule())->get()->where($scheduleFilters)->all();
            foreach ($schedules as $schedule) {
                // Sınavlar için tam zaman çakışması aranır (aynı başlangıç saatindeki kayıt)
                $items = (new ScheduleItem())->get()->where([
                    'schedule_id' => $schedule->id,
                    'day_index' => $baseItem->day_index,
                    'week_index' => $baseItem->week_index
                ])->all();

                foreach ($items as $item) {
                    if ($this->checkOverlap($baseItem->start_time, $baseItem->end_time, $item->start_time, $item->end_time)) {
                        if (!isset($siblingsKeyed[$item->id])) {
                            $siblingsKeyed[$item->id] = $item;
                        }
                    }
                }
            }
        }

        return array_values($siblingsKeyed);
    }

    /**
     * Verilen item üzerinde belirtilen aralıkları siler (Flatten Timeline Yaklaşımı)
     * todo duration ve break veri tabanından çekilmeli
     */
    private function processItemDeletion(ScheduleItem $item, array $deleteIntervals, array $targetLessonIds = [], int $duration = 50, int $break = 10, bool $deleteOriginal = true): array
    {
        //$this->logger()->debug('Processing item deletion', ['item' => $item, 'deleteIntervals' => $deleteIntervals, 'targetLessonIds' => $targetLessonIds]);
        $startStr = $item->getShortStartTime();
        $endStr = $item->getShortEndTime();

        // 1. Kritik noktaları topla (Zaman çizelgesini düzleştir)
        $points = [$startStr, $endStr];

        // İç slot sınırlarını ekle (Duration ve Break geçişleri)
        $current = strtotime($startStr);
        $endUnix = strtotime($endStr);
        while ($current < $endUnix) {
            // Ders sonu
            $current += ($duration * 60);

            if ($current <= $endUnix) {
                if (!in_array(date("H:i", $current), $points))
                    $points[] = date("H:i", $current);

                // Teneffüs sonu
                if ($current < $endUnix) {
                    $current += ($break * 60);
                    if (!in_array(date("H:i", $current), $points))
                        $points[] = date("H:i", $current);
                }
            }
        }

        foreach ($deleteIntervals as $del) {
            $dStart = substr($del['start'], 0, 5);
            $dEnd = substr($del['end'], 0, 5);

            if ($dStart > $startStr && $dStart < $endStr)
                $points[] = $dStart;
            if ($dEnd > $startStr && $dEnd < $endStr)
                $points[] = $dEnd;
        }
        $points = array_unique($points);
        sort($points);

        $newSegments = [];
        $dataList = $item->data ?: [];

        // 2. dilimler (segments) üzerinden geç
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            if ($pStart >= $pEnd)
                continue;

            // Bu dilimin tipi tespiti
            $diff = (strtotime($pEnd) - strtotime($pStart)) / 60;
            $isBreak = ($diff == $break);

            // Bu dilimin silinmesi isteniyor mu?
            $isDeleteZone = false;
            foreach ($deleteIntervals as $del) {
                if ($del['start'] <= $pStart && $del['end'] >= $pEnd) {
                    $isDeleteZone = true;
                    break;
                }
            }

            $currentData = $dataList;
            $shouldKeep = true;

            if ($isDeleteZone) {
                if (!empty($targetLessonIds)) {
                    // Sadece belirli dersleri çıkar
                    $currentData = array_values(array_filter($dataList, function ($l) use ($targetLessonIds) {
                        return !in_array((int) $l['lesson_id'], $targetLessonIds);
                    }));
                } else {
                    // Tüm item siliniyor (Aralık bazlı tam silme)
                    $currentData = [];
                }
            }

            // Dummy öğeler (Preferred/Unavailable) için data boştur.
            // Eğer data boşsa ama statü special ise, isDeleteZone değilse tutmalıyız.
            $isSpecial = in_array($item->status, ['preferred', 'unavailable']);
            $wasPreferred = ($item->detail['preferred'] ?? false);

            if (empty($currentData)) {
                if ($isSpecial) {
                    $shouldKeep = !$isDeleteZone;
                } elseif ($wasPreferred && $isDeleteZone) {
                    // Üzerinde ders olan preferred alan siliniyorsa, alanı preferred olarak geri kazan
                    $shouldKeep = true;
                } else {
                    $shouldKeep = false;
                }
            }

            // Önemli: Segment orijinal öğenin zaman aralığı içinde olmalı
            if ($pStart < $startStr || $pEnd > $endStr)
                continue;

            $segments[] = [
                'start' => $pStart,
                'end' => $pEnd,
                'data' => $currentData,
                'isBreak' => $isBreak,
                'shouldKeep' => $shouldKeep
            ];
        }

        // 3. Teneffüs Temizliği (Break Sanitization)
        // Bir teneffüs ancak hem öncesindeki hem sonrasındaki ders tutuluyorsa (keep) tutulur.
        for ($i = 0; $i < count($segments); $i++) {
            if ($segments[$i]['isBreak']) {
                $prevKept = ($i > 0 && $segments[$i - 1]['shouldKeep']);
                $nextKept = ($i < count($segments) - 1 && $segments[$i + 1]['shouldKeep']);

                if (!$prevKept || !$nextKept) {
                    $segments[$i]['shouldKeep'] = false;
                    $segments[$i]['data'] = [];
                }
            }
        }

        // 4. Parçaları birleştir ve yeni itemları oluştur
        $newSegments = [];
        foreach ($segments as $seg) {
            if (!$seg['shouldKeep'])
                continue;

            $lastIdx = count($newSegments) - 1;
            if (
                $lastIdx >= 0 &&
                $newSegments[$lastIdx]['end'] === $seg['start'] &&
                serialize($newSegments[$lastIdx]['data']) === serialize($seg['data'])
            ) {
                $newSegments[$lastIdx]['end'] = $seg['end'];
            } else {
                $newSegments[] = [
                    'start' => $seg['start'],
                    'end' => $seg['end'],
                    'data' => $seg['data']
                ];
            }
        }

        // 5. Değişiklik Kontrolü kaldırıldı çünkü deleteScheduleItems metodu tüm kardeşleri siliyor.
        // Bu yüzden değişmemiş olsa bile yeniden oluşturulması gerekiyor.

        // 6. Veritabanı güncelleme
        if ($deleteOriginal) {
            $item->delete();
        }

        $createdItems = [];
        if (!empty($newSegments)) {
            foreach ($newSegments as $seg) {
                $newItem = new ScheduleItem();
                $newItem->schedule_id = $item->schedule_id;
                $newItem->day_index = $item->day_index;
                $newItem->week_index = $item->week_index;
                $newItem->start_time = $seg['start'];
                $newItem->end_time = $seg['end'];

                if (in_array($item->status, ['preferred', 'unavailable'])) {
                    $newItem->status = $item->status;
                } elseif ($item->detail['preferred'] ?? false) {
                    // Eğer aslen preferred olan bir dersin parçasıysa ve data boşalmışsa (yukarıdaki shouldKeep sayesinde buraya gelir)
                    // statüsünü tekrar preferred yap
                    if (empty($seg['data'])) {
                        $newItem->status = 'preferred';
                    } else {
                        // Hala ders varsa (kısmi silme) status ders tipine göre belirlenir
                        $isGroup = false;
                        if (!empty($seg['data'])) {
                            foreach ($seg['data'] as $d) {
                                $lessonId = $d['lesson_id'] ?? null;
                                if ($lessonId) {
                                    $lesson = (new \App\Models\Lesson())->find($lessonId);
                                    if ($lesson && $lesson->group_no > 0) {
                                        $isGroup = true;
                                        break;
                                    }
                                }
                            }
                        }
                        $newItem->status = $isGroup ? 'group' : 'single';
                    }
                } else {
                    $isGroup = false;
                    if (!empty($seg['data'])) {
                        foreach ($seg['data'] as $d) {
                            $lessonId = $d['lesson_id'] ?? null;
                            if ($lessonId) {
                                $lesson = (new \App\Models\Lesson())->find($lessonId);
                                    if ($lesson && $lesson->group_no > 0) {
                                        $isGroup = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    $newItem->status = $isGroup ? 'group' : 'single';
                }

                $newItem->data = $seg['data'];
                $newItem->detail = $item->detail;
                $newItem->create();
                $createdItems[] = $newItem;
            }
        }
        return ['deleted' => true, 'created' => $createdItems];
    }
}