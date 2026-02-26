<?php

namespace App\Controllers;

use App\Core\Controller;
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
        $this->logger()->debug("saveExamScheduleItems START. Item Count: " . count($itemsData));

        $isInitiator = !$this->database->inTransaction();
        if ($isInitiator) {
            $this->database->beginTransaction();
        }

        $createdIds = [];
        $affectedLessonIds = [];
        try {
            foreach ($itemsData as $itemData) {
                $dayIndex = $itemData['day_index'];
                $startTime = $itemData['start_time'];
                $endTime = $itemData['end_time'];
                $weekIndex = $itemData['week_index'] ?? 0;

                $lessonId = $itemData['data'][0]['lesson_id'] ?? $itemData['data']['lesson_id'] ?? null;
                $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons', 'parentLesson'])->first();
                if (!$lesson)
                    throw new Exception("Ders bulunamadı");

                // Hedef Schedule bilgileri
                $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
                $semester = $targetSchedule->semester;
                $academicYear = $targetSchedule->academic_year;

                // 1. ADIM: Program ve Ders Sahiplerini Belirle (Parent ve tüm Children)
                $programOwners = [];
                $mainLesson = $lesson->parent_lesson_id ? $lesson->parentLesson : $lesson;

                // Ana dersi ekle
                $programOwners[] = ['type' => 'lesson', 'id' => $mainLesson->id];
                $programOwners[] = ['type' => 'program', 'id' => $mainLesson->program_id, 'semester_no' => $mainLesson->semester_no];

                // Çocuk dersleri ekle
                if (!empty($mainLesson->childLessons)) {
                    foreach ($mainLesson->childLessons as $child) {
                        $programOwners[] = ['type' => 'lesson', 'id' => $child->id];
                        $programOwners[] = ['type' => 'program', 'id' => $child->program_id, 'semester_no' => $child->semester_no];
                    }
                }
                // Unique owners (bazı dersler aynı programda olabilir)
                $uniqueProgramOwners = [];
                foreach ($programOwners as $po) {
                    $key = $po['type'] . '_' . $po['id'] . '_' . ($po['semester_no'] ?? '');
                    $uniqueProgramOwners[$key] = $po;
                }

                // 2. ADIM: Çakışma Kontrolü Yap
                $errors = [];
                // Her bir item için çakışma kontrolü yap (saveScheduleItems'da olduğu gibi benzer bir yapı)
                $this->checkItemConflict($itemData, $errors);

                if (!empty($errors)) {
                    $errors = array_unique($errors);
                    throw new Exception(implode("\n", $errors));
                }

                // 2. ADIM: Program ve Ders Kayıtlarını Yap (Süzülmüş veri ile)
                $itemGroupedIds = [];
                $primaryProgramItemId = null; // Diğer kardeşler (Hoca/Sınıf) için referans

                foreach ($uniqueProgramOwners as $owner) {
                    $scheduleFilters = [
                        'owner_type' => $owner['type'],
                        'owner_id' => $owner['id'],
                        'semester' => $semester,
                        'academic_year' => $academicYear,
                        'type' => $targetSchedule->type,
                        'semester_no' => ($owner['type'] == 'program') ? $owner['semester_no'] : null
                    ];
                    $relSchedule = (new Schedule())->firstOrCreate($scheduleFilters);

                    // VERİ SÜZME: Program ve Ders için sadece lesson_id kalacak
                    $filteredData = [
                        [
                            'lesson_id' => $mainLesson->id, // Her zaman ana ders ID'si referans alınabilir veya orijinal lessonId
                            'lecturer_id' => null,
                            'classroom_id' => null
                        ]
                    ];

                    $newItem = new ScheduleItem();
                    $newItem->schedule_id = $relSchedule->id;
                    $newItem->day_index = $dayIndex;
                    $newItem->week_index = $weekIndex;
                    $newItem->start_time = $startTime;
                    $newItem->end_time = $endTime;
                    $newItem->status = 'single';
                    $newItem->data = $filteredData;
                    $newItem->detail = $itemData['detail']; // Atama bilgileri burada kalsın
                    $newItem->create();

                    $itemGroupedIds[$owner['type']][] = $newItem->id;

                    // Eğer bu asıl hedef program ise ID'yi sakla
                    if ($relSchedule->id == $targetSchedule->id) {
                        $primaryProgramItemId = $newItem->id;
                    }
                }

                // 3. ADIM: Gözetmen (User) ve Derslik (Classroom) Kayıtlarını Yap (Tam veri + Referans)
                $assignments = $itemData['detail']['assignments'] ?? [];
                foreach ($assignments as $assignment) {
                    $assignmentOwners = [
                        ['type' => 'user', 'id' => $assignment['observer_id'], 'classroom_id' => $assignment['classroom_id']],
                        ['type' => 'classroom', 'id' => $assignment['classroom_id'], 'observer_id' => $assignment['observer_id']]
                    ];

                    foreach ($assignmentOwners as $ao) {
                        $scheduleFilters = [
                            'owner_type' => $ao['type'],
                            'owner_id' => $ao['id'],
                            'semester' => $semester,
                            'academic_year' => $academicYear,
                            'type' => $targetSchedule->type,
                            'semester_no' => null
                        ];
                        $relSchedule = (new Schedule())->firstOrCreate($scheduleFilters);

                        // TAM VERİ: Hoca ve Derslik için tüm ID'ler korunur
                        $fullData = [
                            [
                                'lesson_id' => $lessonId,
                                'lecturer_id' => ($ao['type'] == 'user') ? $ao['id'] : $ao['observer_id'],
                                'classroom_id' => ($ao['type'] == 'classroom') ? $ao['id'] : $ao['classroom_id']
                            ]
                        ];

                        $newItem = new ScheduleItem();
                        $newItem->schedule_id = $relSchedule->id;
                        $newItem->day_index = $dayIndex;
                        $newItem->week_index = $weekIndex;
                        $newItem->start_time = $startTime;
                        $newItem->end_time = $endTime;
                        $newItem->status = 'single';
                        $newItem->data = $fullData;

                        // REFERANS: Program tablosundaki ana item ID'sini ekle
                        $newItem->detail = [
                            'program_item_id' => $primaryProgramItemId,
                            'reference_type' => 'exam_assignment'
                        ];
                        $newItem->create();
                        $itemGroupedIds[$ao['type']][] = $newItem->id;
                    }
                }
                $createdIds[] = $itemGroupedIds;
                $affectedLessonIds[] = $mainLesson->id;
            }

            /* 4. ADIM: Kapasite Kontrolü
            foreach (array_unique($affectedLessonIds) as $id) {
                $checkLesson = (new Lesson())->find($id);
                if ($checkLesson) {
                    $checkLesson->IsScheduleComplete($targetSchedule->type);
                    if ($checkLesson->remaining_size < 0) {
                        throw new Exception("{$checkLesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Fazla: " . abs($checkLesson->remaining_size) . " kişi)");
                    }
                }
            }*/

            if ($isInitiator) {
                $this->database->commit();
            }

            // Log the action
            $user = (new UserController())->getCurrentUser();
            $username = $user ? $user->getFullName() : "Sistem";
            $scheduleId = $itemsData[0]['schedule_id'] ?? null;
            $schedule = $scheduleId ? (new Schedule())->find($scheduleId) : null;
            $screenName = $schedule ? $schedule->getScheduleScreenName() : "";
            $typeLabel = $schedule ? $schedule->getScheduleTypeName() : "sınav";

            // Tüm geçerli derslerin isimlerini topla
            $lessonNames = [];
            foreach ($itemsData as $item) {
                $lId = $item['data'][0]['lesson_id'] ?? null;
                if ($lId) {
                    $lessonObj = (new Lesson())->find($lId);
                    if ($lessonObj) {
                        $name = $lessonObj->getFullName();
                        if (!in_array($name, $lessonNames)) {
                            $lessonNames[] = $name;
                        }
                    }
                }
            }
            $lessonName = !empty($lessonNames) ? implode(", ", $lessonNames) : "Bilinmeyen Ders";

            $this->logger()->info("$username $typeLabel programını düzenledi: Eklendi/Güncellendi. Program: $screenName, Ders: $lessonName", $this->logContext());

            return $createdIds;
        } catch (\Throwable $e) {
            if ($isInitiator) {
                $this->database->rollBack();
            }
            $this->logger()->error("Save Exam Schedule Items Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     *
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return array
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): array
    {
        $this->logger()->debug("Using ScheduleService::saveScheduleItems", $this->logContext());
        $service = new ScheduleService();
        $result = $service->saveScheduleItems($itemsData);
        return $this->formatServiceResultToLegacy($result);
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
}
