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
    public function availableLessons(Schedule $schedule, bool $onlyTable = false): array
    {
        if ($onlyTable && in_array($schedule->owner_type, ['user', 'classroom', 'lesson'])) {
            // Sadece tablo modunda (profil sayfaları vb.) Preferred ve Unavailable kartlarını döndür
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


        /**
         * Programa ait tüm derslerin program tamamlanma durumları kontrol ediliyor.
         * @var Lesson $lesson Model allmetodu sonucu oluşan sınıfı PHP strom tanımıyor. otomatik tamamlama olması için ekliyorum
         */
        foreach ($lessonsList as $lesson) {
            if (!$lesson->IsScheduleComplete($schedule->type)) {
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
    private function prepareScheduleRows(Schedule $schedule, $type = "html", $maxDayIndex = null): array
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
        if (in_array($schedule->type, $examTypes)) {
            $duration = getSettingValue('duration', 'exam', 30);
            $break = getSettingValue('break', 'exam', 0);
            // 08:00–17:00 arası 
            $start = new \DateTime('08:00');
            $end = new \DateTime('17:00');
            while ($start < $end) {
                $slotStartTime = clone $start;
                $slotEndTime = (clone $start)->modify("+$duration minutes");
                $scheduleRows[] = [
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
                $scheduleRows[] = [
                    'slotStartTime' => $slotStartTime,
                    'slotEndTime' => $slotEndTime,
                    'days' => $this->generateEmptyWeek($type, $maxDayIndex)
                ];
                $start = (clone $slotEndTime)->modify("+$break minutes"); // tenefüs arası
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

            foreach ($scheduleRows as &$row) {
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
    private function prepareScheduleCard($filters, bool $only_table = false): string
    {
        //$this->logger()->debug("Prepare Schedule Card için Filter alındı", ['filters' => $filters]);
        $filters = $this->validator->validate($filters, "prepareScheduleCard");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır (Genel Program)
        if (in_array($filters['owner_type'], ['user', 'classroom', 'lesson'])) {
            $filters['semester_no'] = null;
        }

        if (is_array($filters['semester_no'])) {
            $availableLessons = [];
            $scheduleRows = [];

            foreach ($filters['semester_no'] as $semester_no) {
                $scheduleFilters = $filters;
                $scheduleFilters['semester_no'] = $semester_no;
                $schedule = (new Schedule())->firstOrCreate($scheduleFilters);
                $availableLessons = ($only_table and in_array($filters['owner_type'], ['program'])) ? [] : array_merge($availableLessons, $this->availableLessons($schedule, $only_table));
                /**
                 * birden fazla schedule row içindeki bilgileri birleştiriyoruz.
                 * Gemini yaptı. biraz karışık ama iş görüyor.
                 */
                $currentRows = $this->prepareScheduleRows($schedule, "html");

                if (empty($scheduleRows)) {
                    $scheduleRows = $currentRows;
                } else {
                    foreach ($currentRows as $rowIndex => $row) {
                        if (!isset($scheduleRows[$rowIndex])) {
                            $scheduleRows[$rowIndex] = $row;
                            continue;
                        }

                        foreach ($row['days'] as $dayIndex => $dayContent) {
                            if (empty($dayContent)) {
                                continue;
                            }

                            if (empty($scheduleRows[$rowIndex]['days'][$dayIndex])) {
                                $scheduleRows[$rowIndex]['days'][$dayIndex] = $dayContent;
                            } else {
                                // Hedefin dizi olduğundan emin ol
                                if (!is_array($scheduleRows[$rowIndex]['days'][$dayIndex])) {
                                    $scheduleRows[$rowIndex]['days'][$dayIndex] = [$scheduleRows[$rowIndex]['days'][$dayIndex]];
                                }

                                // Kaynağı birleştir
                                if (is_array($dayContent)) {
                                    $scheduleRows[$rowIndex]['days'][$dayIndex] = array_merge($scheduleRows[$rowIndex]['days'][$dayIndex], $dayContent);
                                } else {
                                    $scheduleRows[$rowIndex]['days'][$dayIndex][] = $dayContent; // Tek öğe ekle
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $schedule = (new Schedule())->firstOrCreate($filters);
            $availableLessons = ($only_table and in_array($filters['owner_type'], ['program'])) ? [] : $this->availableLessons($schedule, $only_table);
            $scheduleRows = $this->prepareScheduleRows($schedule, "html");
        }

        $availableLessonsHTML = View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'owner_type' => $filters['owner_type'] ?? null
        ]);

        $createTableHeaders = function () use ($filters): array {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = [];
            $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
            $type = in_array($filters['type'], $examTypes) ? 'exam' : 'lesson';
            //$this->logger()->debug("Schedule Table Headers için Type alındı", ['type' => $type]);
            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
            for ($i = 0; $i <= $maxDayIndex; $i++) {
                $headers[] = '<th>' . $days[$i] . '</th>';
            }
            return $headers;
        };

        $scheduleTableHTML = View::renderPartial('admin', 'schedules', 'scheduleTable', [
            'scheduleRows' => $scheduleRows,
            'dayHeaders' => $createTableHeaders(),
            'schedule' => $schedule,
            'only_table' => $only_table
        ]);

        $ownerName = match ($filters['owner_type']) {
            'user' => (new User())->find($filters['owner_id'])->getFullName(),
            'program' => (new Program())->find($filters['owner_id'])->name,
            'classroom' => (new Classroom())->find($filters['owner_id'])->name,
            'lesson' => (new Lesson())->find($filters['owner_id'])->getFullName(),
            default => ""
        };

        //Semester No dizi ise dönemler birleştirilmiş demektir. Birleştirilmişse Başlık olarak Ders programı yazar
        $cardTitle = is_array($filters['semester_no']) ? "Ders Programı" : $filters['semester_no'] . " Yarıyıl Programı";
        $dataSemesterNo = is_array($filters['semester_no']) ? "" : 'data-semester-no="' . $filters['semester_no'] . '"';

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
            'only_table' => $only_table
        ]);
    }

    /**
     * Dönem numarasına göre birleştirilmiş yada her bir dönem için Schedule Card oluşturur
     * @param array $filters
     * @param bool $only_table
     * @return string
     * @throws Exception
     */
    public function getSchedulesHTML(array $filters = [], bool $only_table = false): string
    {
        $filters = $this->validator->validate($filters, "getSchedulesHTML");
        $HTMLOut = "";

        if (key_exists("semester_no", $filters) and is_array($filters['semester_no'])) {
            // birleştirilmiş dönem
            $HTMLOut .= $this->prepareScheduleCard($filters, $only_table);
        } elseif (in_array($filters['owner_type'], ['user', 'classroom', 'lesson'])) {
            // Hoca, Derslik ve Ders programları için tek bir genel program oluşturulur
            $filters['semester_no'] = null;
            $HTMLOut .= $this->prepareScheduleCard($filters, $only_table);
        } else {
            $currentSemesters = getSemesterNumbers($filters["semester"]);
            foreach ($currentSemesters as $semester_no) {
                $filters['semester_no'] = $semester_no;
                $HTMLOut .= $this->prepareScheduleCard($filters, $only_table);
            }
        }

        return $HTMLOut;
    }

    /********************************
     * KAYIT VE GÜNCELLEME İŞLEMLERİ
     ********************************/
    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return array
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): array
    {
        $this->logger()->debug("saveScheduleItems START. Item Count: " . count($itemsData));
        $this->database->beginTransaction();
        $createdIds = [];
        try {
            foreach ($itemsData as $itemData) {
                $this->logger()->debug("saveScheduleItems: Processing item", $this->logContext(['itemData' => $itemData]));
                // 1. İlgili Schedule'ları bul (Çakışma kontrolü için)
                $dayIndex = $itemData['day_index'];
                $startTime = $itemData['start_time'];
                $endTime = $itemData['end_time'];

                $isDummy = in_array($itemData['status'], ['preferred', 'unavailable']);

                $lessonId = !$isDummy ? $itemData['data']['lesson_id'] : null;
                $lecturerId = !$isDummy ? $itemData['data']['lecturer_id'] : null;
                $classroomId = !$isDummy ? $itemData['data']['classroom_id'] : null;

                $lesson = !$isDummy ? (new Lesson())->find($lessonId) : null;

                // Kontrol edilecek schedule sahipleri
                $owners = [];
                if ($isDummy) {
                    $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
                    if ($targetSchedule) {
                        $owners[$targetSchedule->owner_type] = $targetSchedule->owner_id;
                    }
                } else {
                    $owners = [
                        'user' => $lecturerId,
                        'classroom' => ($lesson->classroom_type == 3) ? null : $classroomId, // UZEM ise derslik programı oluşturma
                        'program' => $lesson->program_id,
                        'lesson' => $lesson->id // Kullanıcı isteği üzerine eklendi
                    ];
                }

                // Item'in ekleneceği Schedule'ı bul
                $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
                $semester = $targetSchedule->semester;
                $academicYear = $targetSchedule->academic_year;

                // Tercih edilen alan çakışması yaşanan programların ve açıklamaların listesi
                $preferredConflictSchedules = []; // [schedule_id => description]
                $targetSchedules = []; // Her ders saati için hedef program listesini sıfırla

                // Tüm ilgili schedulelarda çakışma ara ve kayıt edilecek schedule'ları hazırla
                foreach ($owners as $ownerType => $ownerId) {
                    if (!$ownerId)
                        continue;

                    $scheduleFilters = [
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'semester' => $semester,
                        'academic_year' => $academicYear,
                        'type' => $targetSchedule->type
                    ];

                    // Program için semester_no önemli (Diğerleri için null)
                    if ($ownerType == 'program') {
                        $scheduleFilters['semester_no'] = $lesson ? $lesson->semester_no : $targetSchedule->semester_no;
                    } else {
                        $scheduleFilters['semester_no'] = null;
                    }

                    // İlgili schedule'ı bul veya oluştur
                    $relatedSchedule = (new Schedule())->firstOrCreate($scheduleFilters);
                    $targetSchedules[] = $relatedSchedule;

                    $existingItems = (new ScheduleItem())->get()->where([
                        'schedule_id' => $relatedSchedule->id,
                        'day_index' => $dayIndex
                    ])->all();

                    foreach ($existingItems as $existingItem) {
                        if ($this->checkOverlap($startTime, $endTime, $existingItem->start_time, $existingItem->end_time)) {
                            // Çakışma Var: Çözümle
                            if ($existingItem->status == 'preferred') {
                                $this->resolvePreferredConflict($startTime, $endTime, $existingItem);
                                $preferredConflictSchedules[$relatedSchedule->id] = $existingItem->detail['description'] ?? null;
                            } else {
                                // preferred değilse standart conflict check (hata fırlatabilir)
                                if (!$isDummy) {
                                    $this->resolveConflict($itemData, $existingItem, $lesson);
                                }
                            }
                        }
                    }
                }

                // 2. Yeni Item'i Tüm İlgili Schedule'lara Kaydet
                $itemGroupedIds = [];
                foreach ($targetSchedules as $schedule) {
                    // Bu programa özel metadata kontrolü
                    $currentDetail = $itemData['detail'] ?? null;
                    if (array_key_exists($schedule->id, $preferredConflictSchedules)) {
                        if (is_null($currentDetail)) {
                            $currentDetail = ['preferred' => true];
                        } elseif (is_array($currentDetail)) {
                            $currentDetail['preferred'] = true;
                        }

                        // Eğer açıklama varsa onu da ekle
                        $description = $preferredConflictSchedules[$schedule->id];
                        if ($description) {
                            $currentDetail['description'] = $description;
                        }
                    }

                    // Data formatını hazırla (Tekil array içinde array yapısı)
                    $validData = [];
                    if (isset($itemData['data']['lesson_id'])) {
                        $validData = [$itemData['data']];
                    } else {
                        $validData = $itemData['data'];
                    }

                    $validDetail = $currentDetail;

                    if ($itemData['status'] === 'group') {
                        // Group statusunde ise merge/split işlemi yap
                        $groupIds = $this->processGroupItemSaving(
                            $schedule,
                            $itemData['day_index'],
                            $itemData['start_time'],
                            $itemData['end_time'],
                            $validData,
                            $validDetail
                        );
                        $itemGroupedIds[$schedule->owner_type] = $groupIds;
                    } else {
                        // Diğer durumlar (single, preferred, unavailable) için direkt create
                        $newItem = new ScheduleItem();
                        $newItem->schedule_id = $schedule->id;
                        $newItem->day_index = $itemData['day_index'];
                        $newItem->start_time = $itemData['start_time'];
                        $newItem->end_time = $itemData['end_time'];
                        $newItem->status = $itemData['status'];
                        $newItem->data = $validData;
                        $newItem->detail = $validDetail;
                        $newItem->create();
                        $itemGroupedIds[$schedule->owner_type] = [$newItem->id];
                    }
                }
                $createdIds[] = $itemGroupedIds;
            }
            $this->database->commit();
            return $createdIds;
        } catch (\Throwable $e) {
            $this->database->rollBack();
            $this->logger()->error("Save Schedule Items Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'itemData' => $itemData ?? 'N/A'
            ]);
            throw $e;
        }
    }

    /**
     * Group statusundaki itemleri birleştirip yeniden oluşturur.
     * Çakışan zaman dilimlerinde verileri birleştirir, diğer dilimlerde ayırır.
     */
    private function processGroupItemSaving(Schedule $schedule, int $dayIndex, string $startTime, string $endTime, array $newData, ?array $newDetail): array
    {
        // 1. İlgili günün tüm 'group' itemlerini çek
        $allDayItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $schedule->id,
            'day_index' => $dayIndex,
            'status' => 'group'
        ])->all();

        // Sadece tarih aralığı çakışanları filtrele
        $involvedItems = array_filter($allDayItems, function ($item) use ($startTime, $endTime) {
            return $this->checkOverlap($startTime, $endTime, $item->getShortStartTime(), $item->getShortEndTime());
        });

        // Eğer hiç çakışma yoksa direkt oluştur
        if (empty($involvedItems)) {
            $newItem = new ScheduleItem();
            $newItem->schedule_id = $schedule->id;
            $newItem->day_index = $dayIndex;
            $newItem->start_time = $startTime;
            $newItem->end_time = $endTime;
            $newItem->status = 'group';
            $newItem->data = $newData;
            $newItem->detail = $newDetail;
            $newItem->create();
            return [$newItem->id];
        }

        // 2. Zaman çizelgesini düzleştir (Flatten Timeline)
        // Tüm başlangıç ve bitiş noktalarını topla
        $startTime = substr($startTime, 0, 5);
        $endTime = substr($endTime, 0, 5);
        $points = [$startTime, $endTime];
        foreach ($involvedItems as $item) {
            $points[] = $item->getShortStartTime();
            $points[] = $item->getShortEndTime();
        }
        $points = array_unique($points);
        sort($points);

        // 3. Aralıkları yeniden oluştur (Rebuild Intervals)
        $pendingItems = [];

        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            // Aralık uzunluğu kontrolü (Hatalı nokta ihtimaline karşı)
            if ($pStart >= $pEnd)
                continue;

            $mergedData = [];
            $mergedDetail = [];

            // Yeni veri bu aralığı kapsıyor mu?
            if ($startTime <= $pStart && $endTime >= $pEnd) {
                $mergedData = array_merge($mergedData, $newData);
                if ($newDetail) {
                    $mergedDetail = array_merge($mergedDetail, $newDetail);
                }
            }

            // Mevcut itemler bu aralığı kapsıyor mu?
            foreach ($involvedItems as $item) {
                if ($item->getShortStartTime() <= $pStart && $item->getShortEndTime() >= $pEnd) {
                    $itemData = $item->data;
                    if (is_array($itemData)) {
                        $mergedData = array_merge($mergedData, $itemData);
                    }

                    $itemDetail = $item->detail;
                    if (is_array($itemDetail)) {
                        $mergedDetail = array_merge($mergedDetail, $itemDetail);
                    }
                }
            }

            // Data varsa listeye ekle
            if (!empty($mergedData)) {
                // Duplicate lesson'ları temizle (lesson_id bazlı)
                $uniqueData = [];
                $seenLessonIds = [];
                foreach ($mergedData as $d) {
                    $lid = $d['lesson_id'] ?? null;
                    if ($lid && !in_array($lid, $seenLessonIds)) {
                        $seenLessonIds[] = $lid;
                        $uniqueData[] = $d;
                    } elseif (!$lid) {
                        $uniqueData[] = $d;
                    }
                }

                // Optimization: Eğer bir önceki item ile datalar ve detail aynı ise zaman aralığını uzat
                $lastIdx = count($pendingItems) - 1;
                if ($lastIdx >= 0) {
                    $lastItem = &$pendingItems[$lastIdx];
                    if (
                        $lastItem['end'] == $pStart &&
                        json_encode($lastItem['data']) === json_encode($uniqueData) &&
                        json_encode($lastItem['detail']) === json_encode($mergedDetail)
                    ) {

                        $lastItem['end'] = $pEnd;
                        continue;
                    }
                }

                $pendingItems[] = [
                    'start' => $pStart,
                    'end' => $pEnd,
                    'data' => $uniqueData,
                    'detail' => $mergedDetail
                ];
            }
        }

        // 4. Veritabanı İşlemleri
        // Eski itemleri sil
        foreach ($involvedItems as $item) {
            $item->delete();
        }

        $createdGroupIds = [];
        // Yeni itemleri oluştur
        foreach ($pendingItems as $pItem) {
            $newItem = new ScheduleItem();
            $newItem->schedule_id = $schedule->id;
            $newItem->day_index = $dayIndex;
            $newItem->start_time = $pItem['start'];
            $newItem->end_time = $pItem['end'];
            $newItem->status = 'group';
            $newItem->data = $pItem['data'];
            $newItem->detail = !empty($pItem['detail']) ? $pItem['detail'] : null;
            $newItem->create();
            $createdGroupIds[] = $newItem->id;
        }
        return $createdGroupIds;
    }
    /**
     * Preferred item ile çakışma durumunda preferred item'i günceller (kısaltır veya böler)
     */
    private function resolvePreferredConflict(string $newStart, string $newEnd, ScheduleItem $preferredItem): void
    {
        // Zamanları H:i formatına normalize et
        $newStart = substr($newStart, 0, 5);
        $newEnd = substr($newEnd, 0, 5);
        $prefStart = $preferredItem->getShortStartTime();
        $prefEnd = $preferredItem->getShortEndTime();

        // Durum 1: Yeni item preferred item'i tamamen kapsıyor -> Sil
        if ($newStart <= $prefStart && $newEnd >= $prefEnd) {
            $preferredItem->delete();
            return;
        }

        // Durum 2: Yeni item son taraftan örtüşüyor (Örn: Pref 10-12, New 11-13 -> Pref 10-11)
        if ($newStart > $prefStart && $newStart < $prefEnd && $newEnd >= $prefEnd) {
            $preferredItem->end_time = $newStart;
            // Eğer süre sıfıra indiyse sil
            if ($preferredItem->getShortStartTime() >= $preferredItem->getShortEndTime()) {
                $preferredItem->delete();
            } else {
                $preferredItem->update();
            }
            return;
        }

        // Durum 3: Yeni item baş taraftan örtüşüyor (Örn: Pref 10-12, New 09-11 -> Pref 11-12)
        if ($newStart <= $prefStart && $newEnd > $prefStart && $newEnd < $prefEnd) {
            $preferredItem->start_time = $newEnd;
            // Eğer süre sıfıra indiyse sil
            if ($preferredItem->getShortStartTime() >= $preferredItem->getShortEndTime()) {
                $preferredItem->delete();
            } else {
                $preferredItem->update();
            }
            return;
        }

        // Durum 4: Yeni item ortada (Örn: Pref 10-14, New 11-12 -> Pref 10-11 VE Pref 12-14)
        if ($newStart > $prefStart && $newEnd < $prefEnd) {
            // Mevcutu kısalt (Sol parça)
            $preferredItem->end_time = $newStart;
            $preferredItem->update();

            // Yeni parça oluştur (Sağ parça)
            $rightPart = new ScheduleItem();
            $rightPart->schedule_id = $preferredItem->schedule_id;
            $rightPart->day_index = $preferredItem->day_index;
            $rightPart->start_time = $newEnd;
            $rightPart->end_time = $prefEnd;
            $rightPart->status = 'preferred';
            // data ve detail kopyalanıyor
            $rightPart->data = $preferredItem->data;
            $rightPart->detail = $preferredItem->detail;
            $rightPart->create();
            return;
        }
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

        foreach ($items as $itemData) {
            $this->checkItemConflict($itemData);
        }

        return true;
    }

    /**
     * Tek bir item için tüm olasılıkları (Hoca, Sınıf, Program, Ders) kontrol eder
     * İlgili schedule ve itemleri bulup çakışma kontrolüne gönderir
     * @param array $itemData
     * @throws Exception
     */
    private function checkItemConflict(array $itemData): void
    {
        $lessonId = $itemData['data']['lesson_id'];
        $lecturerId = $itemData['data']['lecturer_id'];
        $classroomId = $itemData['data']['classroom_id'];
        $dayIndex = $itemData['day_index'];
        $startTime = $itemData['start_time'];
        $endTime = $itemData['end_time'];

        $lesson = (new Lesson())->find($lessonId);
        if (!$lesson)
            throw new Exception("Ders bulunamadı");

        // Kontrol edilecek schedule sahipleri
        $owners = [
            'user' => $lecturerId,
            'classroom' => ($lesson->classroom_type == 3) ? null : $classroomId, // UZEM ise derslik çakışmasına bakma
            'program' => $lesson->program_id,
            'lesson' => $lesson->id
        ];

        // Item'in ekleneceği Schedule'ı bul (Dönem ve Yıl bilgisi için)
        $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
        if (!$targetSchedule)
            throw new Exception("Hedef Program bulunamadı");

        $semester = $targetSchedule->semester;
        $academicYear = $targetSchedule->academic_year;

        foreach ($owners as $ownerType => $ownerId) {
            if (!$ownerId)
                continue;

            $scheduleFilters = [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'type' => $targetSchedule->type
            ];

            if ($ownerType == 'program') {
                $scheduleFilters['semester_no'] = $lesson->semester_no;
            }

            $relatedSchedules = (new Schedule())->get()->where($scheduleFilters)->all();

            foreach ($relatedSchedules as $relatedSchedule) {
                // İlgili schedule ve gün için itemları getir
                $dayItems = (new ScheduleItem())->get()->where([
                    'schedule_id' => $relatedSchedule->id,
                    'day_index' => $dayIndex
                ])->all();

                foreach ($dayItems as $existingItem) {
                    // Zaman çakışması kontrolü
                    if ($this->checkOverlap($startTime, $endTime, $existingItem->start_time, $existingItem->end_time)) {
                        $this->resolveConflict($itemData, $existingItem, $lesson);
                    }
                }
            }
        }
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
     * @throws Exception Çakışma kuralı ihlal edilirse
     */
    private function resolveConflict(array $newItemData, ScheduleItem $existingItem, Lesson $newLesson): void
    {
        // Kendi kendisiyle çakışıyorsa (update durumu vs) yoksay
        if (isset($newItemData['id']) && $newItemData['id'] == $existingItem->id) {
            return;
        }

        // Status Kontrolü
        switch ($existingItem->status) {
            case 'unavailable':
                throw new Exception("Bu saat aralığı uygun değil.");
            case 'single':
                // Single ders varsa üzerine ders eklenemez
                throw new Exception("Bu saatte zaten bir ders mevcut: " . $this->getLessonNameFromItem($existingItem));
            case 'group':
                // Grup mantığı
                // Yeni ders aynı zamanda grup dersi olmalı (Lesson group_no > 0)
                if ($newLesson->group_no < 1) {
                    throw new Exception("Grup dersi üzerine normal ders eklenemez.");
                }

                // Mevcut gruptaki dersleri kontrol et
                $slotDatas = $existingItem->getSlotDatas();
                foreach ($slotDatas as $sd) {
                    if (!$sd->lesson)
                        continue;

                    // Dersler farklı olmalı
                    if ($sd->lesson->id == $newLesson->id) {
                        throw new Exception("Aynı ders aynı saatte tekrar eklenemez (Grup olsa bile).");
                    }

                    // Grup numaraları farklı olmalı
                    if ($sd->lesson->group_no == $newLesson->group_no) {
                        throw new Exception("Aynı grup numarasına sahip dersler çakışamaz.");
                    }
                }

                // Buraya geldiyse uygundur (Farklı ders, farklı grup, ikisi de grup)
                break;
            case 'preferred':
                // Tercih edilen saat, çakışma yok
                break;
            default:
                throw new Exception("Bilinmeyen durum: " . $existingItem->status);
        }
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
         * dersin derslik türü ile aynı türdeki bütün derslikler scheduleları ile birlikte çağırılacak. ama schedule'ları henüz oluşturulmamış olabileceğinden bütün derslikler alınacak ve owner_type classroom olanve diğer bilgileri gelen schedule ile aynı olan schedule'ler firstOrCreate ile çağırılacak
         * filters['startTime'] ve filters['hours'] bilgisine göre endTime hesaplanacak
         * bütün dersliklerin schedule itemleri arasında belirtilen bağlangıç saati ve hesaplanan bitiş saati arasında bir schedule item varsa o derslik uygun olmayacaktır.
         * bu schedule'lerin 
         */
        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Derslik türünü belirlemek için ders bulunamadı");
        //Derslik türü karma ise Lab ve derslik türleri dahil ediliyor
        $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
        $classrooms = (new Classroom())->get()->where(["type" => ['in' => $classroom_type]])->all();

        $availableClassrooms = [];
        $startTime = new \DateTime($filters['startTime']);
        $endTime = (clone $startTime)->modify('+' . $this->lessonHourToMinute($schedule->type, $filters['hours']) . ' minutes');

        foreach ($classrooms as $classroom) {
            $classroomSchedule = (new Schedule())->firstOrCreate([
                'type' => $schedule->type,
                'owner_type' => 'classroom',
                'owner_id' => $classroom->id,
                'semester_no' => null, // Derslik programları dönemden bağımsızdır
                'semester' => $schedule->semester,
                'academic_year' => $schedule->academic_year
            ]);

            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $classroomSchedule->id,
                'day_index' => $filters['day_index']
            ])->all();

            $isAvailable = true;
            // UZEM (3) tipi sınıflar her zaman uygun sayılır
            if ($classroom->type != 3) {
                foreach ($items as $item) {
                    if ($this->checkOverlap($startTime->format('H:i'), $endTime->format('H:i'), $item->start_time, $item->end_time)) {
                        $isAvailable = false;
                        break;
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

        $times = $this->generateTimesArrayFromText($filters["time"], $filters["hours"], $filters["type"]);
        $unavailable_observer_ids = [];

        // O saatte herhangi bir programı olan kullanıcıları bul (owner_type = user)
        $userSchedules = $this->getListByFilters(
            [
                "time" => ['in' => $times],
                "owner_type" => 'user',
                "semester" => $filters['semester'],
                "academic_year" => $filters['academic_year'],
                "type" => $filters['type']
            ]
        );

        foreach ($userSchedules as $schedule) {
            if (!is_null($schedule->{"day" . $filters["day_index"]})) {
                $unavailable_observer_ids[$schedule->owner_id] = true;
            }
        }

        // Anahtarları diziye dönüştürüyoruz.
        $unavailable_observer_ids = array_keys($unavailable_observer_ids);

        // Eğer hariç tutulacaklar varsa filtreye ekle
        if (!empty($unavailable_observer_ids)) {
            $observerFilters["!id"] = ['in' => $unavailable_observer_ids];
        }

        return (new UserController())->getListByFilters($observerFilters);
    }

    /*********************
     *  SİLME İŞLEMLERİ
     **********************/

    public function deleteScheduleItems(array $items): array
    {

        //$this->logger()->debug("Delete ScheduleItems Data: ", ['items' => $items]);

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
        $this->database->beginTransaction();
        try {
            foreach ($items as $itemData) {
                $id = (int) ($itemData['id'] ?? 0);
                $this->logger()->debug("Checking Item ID in Loop: $id");
                if (!$id)
                    continue;

                if (in_array($id, $processedSiblingIds)) {
                    $this->logger()->debug("Skipping ID $id (Already processed as sibling)");
                    continue;
                }

                // Ana öğeyi ve program tipini bul
                $scheduleItem = (new ScheduleItem())->where(['id' => $id])->with('schedule')->first();
                if (!$scheduleItem) {
                    $this->logger()->debug("ScheduleItem $id not found in DB.");
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
                    $baseLessonIds = [];
                    foreach ($scheduleItem->getSlotDatas() as $sd) {
                        if ($sd->lesson) {
                            $baseLessonIds[] = (int) $sd->lesson->id;
                        }
                    }
                    $siblings = $this->findSiblingItems($scheduleItem, $baseLessonIds);
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
                                    $targetLessonIds[] = (int) $d['lesson_id'];
                                }
                            }
                        }
                    }
                }
                $targetLessonIds = array_unique($targetLessonIds);

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

                $this->logger()->debug("Processing Sibling Group for ID $id: ", [
                    'siblingCount' => count($siblings),
                    'ids' => $siblingIds,
                    'intervals' => $mergedIntervals
                ]);

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

                // Bu gruptaki herkesi işlendi olarak işaretle (Bir sonraki döngüde mükerrer işlememesi için)
                $processedSiblingIds = array_unique(array_merge($processedSiblingIds, $siblingIds));
                $this->logger()->debug("Finished Sibling Group for ID $id. Processed IDs: ", ['list' => $processedSiblingIds]);
            }
            $this->database->commit();
        } catch (\Exception $e) {
            $this->database->rollBack();
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
    private function findSiblingItems(ScheduleItem $baseItem, array $lessonIds): array
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
                // Lesson owner
                $ownerList[] = ['type' => 'lesson', 'id' => (int) $lesson->id, 'semester_no' => null];

                // Lecturer (User) owner
                if ($slotData->lecturer) {
                    $ownerList[] = ['type' => 'user', 'id' => (int) $slotData->lecturer->id, 'semester_no' => null];
                }

                // Classroom owner
                if ($slotData->classroom) {
                    $ownerList[] = ['type' => 'classroom', 'id' => (int) $slotData->classroom->id, 'semester_no' => null];
                }

                // Program owner
                if ($lesson->program_id) {
                    $ownerList[] = ['type' => 'program', 'id' => (int) $lesson->program_id, 'semester_no' => $lesson->semester_no];
                }
            }
        }

        // 2. Owner listesini unique hale getir
        $uniqueOwners = [];
        foreach ($ownerList as $owner) {
            $key = $owner['type'] . "_" . $owner['id'] . "_" . ($owner['semester_no'] ?? 'null');
            $uniqueOwners[$key] = $owner;
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
                    'day_index' => $baseItem->day_index
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
     * Verilen item üzerinde belirtilen aralıkları siler (Flatten Timeline Yaklaşımı)
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