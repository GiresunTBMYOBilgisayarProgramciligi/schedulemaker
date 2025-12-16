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
            // 08:00–17:00 arası 30 dk slotlar (12:00–13:00 DAHIL)
            $start = new \DateTime('08:00');
            $end = new \DateTime('17:00');
            while ($start < $end) {
                $slotStartTime = clone $start;
                $slotEndTime = (clone $start)->modify('+30 minutes');
                $scheduleRows[] = [
                    'slotStartTime' => $slotStartTime,
                    'slotEndTime' => $slotEndTime,
                    'days' => $this->generateEmptyWeek($type, $maxDayIndex)
                ];

                $start = $slotEndTime;
            }
        } else {
            // 08:00–17:00 arası 30 dk slotlar (12:00–13:00 DAHIL)
            $start = new \DateTime('08:00');
            $end = new \DateTime('17:00');
            while ($start < $end) {
                $slotStartTime = clone $start;
                $slotEndTime = (clone $start)->modify('+50 minutes');
                $scheduleRows[] = [
                    'slotStartTime' => $slotStartTime,
                    'slotEndTime' => $slotEndTime,
                    'days' => $this->generateEmptyWeek($type, $maxDayIndex)
                ];
                $start = (clone $slotEndTime)->modify('+10 minutes'); // tenefüs arası
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

        $this->logger()->debug('Schedule Rows oluşturuldu', ['scheduleRows' => $scheduleRows]);
        return $scheduleRows;
    }

    /**
     * Ders programı tamamlanmamış olan derslerin bilgilerini döner.
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableLessons(Schedule $schedule): array
    {
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

        // Eğer program (curriculum) schedule'ı ise semester_no filtresini ekle
        if ($schedule->semester_no !== null) {
            $lessonFilters['semester_no'] = $schedule->semester_no;
        }
        $lessonsList = (new Lesson())->get()->where($lessonFilters)->with(['lecturer', 'program'])->all();


        // Ders programı için mevcut saat bazlı mantık
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
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    private function prepareScheduleCard($filters, bool $only_table = false): string
    {
        $this->logger()->debug("Prepare Schedule Card için Filter alındı", ['filters' => $filters]);
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
                $availableLessons = $only_table ? [] : array_merge($availableLessons, $this->availableLessons($schedule));
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
            $availableLessons = $only_table ? [] : $this->availableLessons($schedule);
            $scheduleRows = $this->prepareScheduleRows($schedule, "html");
        }

        $availableLessonsHTML = View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule
        ]);

        $createTableHeaders = function () use ($filters): array {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = [];
            $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
            $type = in_array($filters['type'], $examTypes) ? 'exam' : 'lesson';
            $this->logger()->debug("Schedule Table Headers için Type alındı", ['type' => $type]);
            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
            for ($i = 0; $i <= $maxDayIndex; $i++) {
                $headers[] = '<th>' . $days[$i] . '</th>';
            }
            return $headers;
        };

        $scheduleTableHTML = View::renderPartial('admin', 'schedules', 'scheduleTable', [
            'scheduleRows' => $scheduleRows,
            'dayHeaders' => $createTableHeaders(),
            'schedule' => $schedule
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

        return View::renderPartial('admin', 'schedules', 'scheduleCard', [
            'schedule' => $schedule,
            'availableLessonsHTML' => $availableLessonsHTML,
            'scheduleTableHTML' => $scheduleTableHTML,
            'ownerName' => $ownerName,
            'cardTitle' => $cardTitle,
            'dataSemesterNo' => $dataSemesterNo
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
                $endTime = clone $currentTime;
                $endTime->modify('+30 minutes');
                $endFormatted = $endTime->format('H.i');
                $schedule[] = "$startFormatted - $endFormatted";
                $currentTime = $endTime;
            } else {
                $endTime = clone $currentTime;
                $endTime->modify('+50 minutes');
                $endFormatted = $endTime->format('H.i');
                $schedule[] = "$startFormatted - $endFormatted";
                $currentTime = $endTime->modify('+10 minutes');
            }
        }

        return $schedule;
    }

    public function lessonHourToMinute($scheduleType, $hours): int
    {
        if ($scheduleType === 'lesson') {
            return $hours * 60;
        } elseif ($scheduleType === 'midterm-exam' || $scheduleType === 'final-exam' || $scheduleType === 'makeup-exam') {
            return $hours * 30;
        }
        return 0;
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek
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

            $count = (new ScheduleItem())->where([
                'schedule_id' => $classroomSchedule->id,
                'day_index' => $filters['day_index'],
                'start_time' => ['between' => [$startTime->format('H:i'), $endTime->format('H:i')]]
            ])->count();
            if ($count > 0) {
                continue;
            }
            $availableClassrooms[] = $classroom;
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

    /**
     * Programa eklenmek isteyen itemler için çakışma kontrolü yapar
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        $filters = $this->validator->validate($filters, "checkScheduleCrash");
        $this->logger()->debug("Check Schedule Crash Filters: ", $this->logContext($filters));

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
            'classroom' => $classroomId,
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

                // Dersler farklı olmalı
                $existingLessonId = $existingItem->data['lesson_id'] ?? null;
                if ($existingLessonId == $newLesson->id) {
                    throw new Exception("Aynı ders aynı saatte tekrar eklenemez (Grup olsa bile).");
                }

                // Grup numaraları farklı olmalı (Bunu Lesson modelinden kontrol edebiliriz eğer item data içinde group_no yoksa)
                // Existing item'in dersini bulalım
                $existingLesson = (new Lesson())->find($existingLessonId);
                if ($existingLesson && $existingLesson->group_no == $newLesson->group_no) {
                    throw new Exception("Aynı grup numarasına sahip dersler çakışamaz.");
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
     * Hata mesajlarında göstermek için Item içindeki lesson_id'den ders adını bulur
     * @param ScheduleItem $item
     * @return string Ders Adı (Kodu) veya Bilinmeyen
     */
    private function getLessonNameFromItem(ScheduleItem $item): string
    {
        if (isset($item->data['lesson_id'])) {
            $l = (new Lesson())->find($item->data['lesson_id']);
            return $l ? $l->getFullName() : "Bilinmeyen Ders";
        }
        return "Bilinmeyen Öğe";
    }

    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return bool
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): bool
    {
        $this->database->beginTransaction();
        try {
            foreach ($itemsData as $itemData) {
                // 1. İlgili Schedule'ları bul (Çakışma kontrolü için)
                $lessonId = $itemData['data']['lesson_id'];
                $lecturerId = $itemData['data']['lecturer_id'];
                $classroomId = $itemData['data']['classroom_id'];
                $dayIndex = $itemData['day_index'];
                $startTime = $itemData['start_time'];
                $endTime = $itemData['end_time'];

                $lesson = (new Lesson())->find($lessonId);
                // Kontrol edilecek schedule sahipleri
                $owners = [
                    'user' => $lecturerId,
                    'classroom' => $classroomId,
                    'program' => $lesson->program_id,
                    'lesson' => $lesson->id // Kullanıcı isteği üzerine eklendi
                ];

                // Item'in ekleneceği Schedule'ı bul
                $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
                $semester = $targetSchedule->semester;
                $academicYear = $targetSchedule->academic_year;

                $targetSchedules = []; // Kayıt yapılacak schedule listesi

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
                        $scheduleFilters['semester_no'] = $lesson->semester_no;
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
                            } else {
                                // preferred değilse standart conflict check (hata fırlatabilir)
                                $this->resolveConflict($itemData, $existingItem, $lesson);
                            }
                        }
                    }
                }

                // 2. Yeni Item'i Tüm İlgili Schedule'lara Kaydet
                foreach ($targetSchedules as $schedule) {
                    $newItem = new ScheduleItem();
                    $newItem->schedule_id = $schedule->id;
                    $newItem->day_index = $itemData['day_index'];
                    $newItem->start_time = $itemData['start_time'];
                    $newItem->end_time = $itemData['end_time'];
                    $newItem->status = $itemData['status']; // muhtemelen 'single' veya 'group'

                    // ScheduleItem modelinde data bir liste olarak tutulur (örn: [ ['lesson_id'=>1], ['lesson_id'=>2] ])
                    // Frontend'den gelen veri tek bir obje olabilir, bu yüzden diziye çeviriyoruz.
                    if (isset($itemData['data']['lesson_id'])) { // Tek bir ders verisi gelmişse
                        $newItem->data = [$itemData['data']];
                    } else {
                        $newItem->data = $itemData['data'];
                    }

                    if (isset($itemData['detail']) && !array_is_list($itemData['detail'])) {
                        $newItem->detail = [$itemData['detail']];
                    } else {
                        $newItem->detail = $itemData['detail'] ?? null;
                    }

                    $newItem->create();
                }
            }
            $this->database->commit();
            return true;
        } catch (Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    /**
     * Preferred item ile çakışma durumunda preferred item'i günceller (kısaltır veya böler)
     */
    private function resolvePreferredConflict(string $newStart, string $newEnd, ScheduleItem $preferredItem): void
    {
        $prefStart = $preferredItem->start_time;
        $prefEnd = $preferredItem->end_time;

        // Durum 1: Yeni item preferred item'i tamamen kapsıyor -> Sil
        if ($newStart <= $prefStart && $newEnd >= $prefEnd) {
            $preferredItem->delete();
            return;
        }

        // Durum 2: Yeni item son taraftan örtüşüyor (Örn: Pref 10-12, New 11-13 -> Pref 10-11)
        if ($newStart > $prefStart && $newStart < $prefEnd && $newEnd >= $prefEnd) {
            $preferredItem->end_time = $newStart;
            $preferredItem->update();
            return;
        }

        // Durum 3: Yeni item baş taraftan örtüşüyor (Örn: Pref 10-12, New 09-11 -> Pref 11-12)
        if ($newStart <= $prefStart && $newEnd > $prefStart && $newEnd < $prefEnd) {
            $preferredItem->start_time = $newEnd;
            $preferredItem->update();
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

    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * Schedule tablosuna yeni kayıt ekler
     * @param array $schedule_arr Yeni schedule verileri
     * @return int Son eklenen verinin id numarasını döner
     * @throws Exception
     */
    public function saveNew(array $schedule_arr): int
    {
        try {
            $new_schedule = new Schedule();
            $new_schedule->fill($schedule_arr);
            $new_schedule->create();
            return $new_schedule->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası) program var gün güncellenecek
                /**
                 * Yeni eklenen programda ekleme yapılan gün bilgisini tutan anahtar
                 */
                $day_key = find_key_starting_with($schedule_arr, "day");
                /**
                 * gün bilgisi çıkartılarak program aranıyor.
                 */
                $updatingSchedule = (new Schedule())->get()->where(array_diff_key($schedule_arr, [$day_key => null]))->first();
                $this->logger()->debug(" Updating Schedule: ", ["updatingSchedule" => $updatingSchedule]);
                // yeni bilgi eklenecek alanın verisinin dizi olup olmadığına bakıyoruz. dizi ise bir ders vardır.
                if (is_array($updatingSchedule->{$day_key})) {
                    $currentData = $updatingSchedule->{$day_key};
                    // Eğer veri listesi ise (birden fazla ders varsa) ilkini al
                    $existingLessonData = isset($currentData[0]) ? $currentData[0] : $currentData;

                    /**
                     * Var olan dersin kodu
                     */
                    $lesson = (new Lesson())->find($existingLessonData['lesson_id']) ?: throw new Exception("Var olan ders bulunamadı");
                    /**
                     * Yeni eklenecek dersin kodu
                     */
                    $newLesson = (new Lesson())->find($new_schedule->{$day_key}['lesson_id']) ?: throw new Exception("Eklenecek ders ders bulunamadı");
                    // Derslerin ikisinin de kodunun son kısmında . ve bir sayı varsa gruplu bir derstir. Bu durumda aynı güne eklenebilir.
                    // grupların farklı olup olmadığının kontrolü javascript tarafında yapılıyor.
                    $isExam = isset($schedule_arr['type']) && in_array($schedule_arr['type'], ['midterm-exam', 'final-exam', 'makeup-exam']);
                    $bothGrouped = preg_match('/\.\d+$/', $lesson->code) === 1 and preg_match('/\.\d+$/', $newLesson->code) === 1;

                    if ($isExam || $bothGrouped) {
                        $currentData = $updatingSchedule->{$day_key};
                        if (isset($currentData[0])) {
                            // Zaten birden fazla ders varsa listeye ekle
                            $currentData[] = $new_schedule->{$day_key};
                            $updatingSchedule->{$day_key} = $currentData;
                        } else {
                            // Tek ders varsa listeye dönüştür
                            $dayData = [];
                            $dayData[] = $currentData;
                            $dayData[] = $new_schedule->{$day_key};
                            $updatingSchedule->{$day_key} = $dayData;
                        }
                    } else {
                        throw new Exception("Dersler gruplu değil bu şekilde kaydedilemez");
                    }
                } else {
                    // Gün verisi dizi değilse null, true yada false olabilir.
                    if ($updatingSchedule->{$day_key} === false) {
                        throw new Exception("Belirtilen gün için ders eklenmesine izin verilmemiş");
                    } else {
                        // ders normal şekilde güncellenecek
                        $updatingSchedule->{$day_key} = $new_schedule->{$day_key};
                    }
                }
                return $this->updateSchedule($updatingSchedule);
            } else {
                throw new Exception($e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * @param Schedule $schedule
     * @return int
     * @throws Exception
     */
    public function updateSchedule(Schedule $schedule): int
    {
        //todo bu fonksiyon yeni model yapısına göre düzenlenecek
        try {
            $scheduleData = $schedule->getArray(['table_name', 'database', 'id'], true);
            //dizi türündeki veriler serialize ediliyor
            array_walk($scheduleData, function (&$value) {
                if (is_array($value)) {
                    $value = serialize($value);
                }
            });
            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($scheduleData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $schedule->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );
            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);
            if ($stmt->rowCount() > 0) {
                $this->logger()->info("Program Güncellendi", $this->logContext(["schedule" => $schedule]));
                return $schedule->id;
            } else {
                throw new Exception("Program Güncellenemedi");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Schedule Çakışması var" . $e->getMessage());
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * @param $filters array silinecek programın veri tabanında bulunması için gerekli veriler.
     * @return array
     * @throws Exception
     */
    public function deleteSchedule(array $filters): array
    {
        $filters = $this->validator->validate($filters, "deleteSchedule");
        $this->logger()->debug("Delete Schedule Filters: ", ["filters" => $filters]);
        $scheduleData = array_diff_key($filters, array_flip(["day", "day_index", "classroom_id"]));// day ve day_index alanları çıkartılıyor
        $this->logger()->debug("Delete Schedule ScheduleData: ", ["scheduleData" => $scheduleData]);
        if ($scheduleData['owner_type'] == "classroom") {
            $classroom = (new Classroom())->find($scheduleData['owner_id']) ?: throw new Exception("Derslik Bulunamadı");
            if ($classroom->type == 3)
                return []; // uzaktan eğitim sınıfı ise programa kaydı yoktur
        }
        $schedules = (new Schedule())->get()->where($scheduleData)->all();

        if (!$schedules) {
            throw new Exception("Silinecek ders programı bulunamadı");
        }
        $results = [];
        foreach ($schedules as $schedule) {
            $this->logger()->debug("Program silinecek", $this->logContext([
                "filters" => $filters,
                "schedules" => $schedules,
                "schedule" => $schedule
            ]));
            /**
             * Eğer dönem numarası belirtilmediyse aktif dönem numaralarınsaki tüm dönemler silinir.
             * todo semester no belirtilemeyen durumlar hangileriydi ? Kullanıcı tercihlerinde silme işleminde semester no yok
             */
            if (!key_exists("semester_no", $filters)) {
                $this->logger()->debug("semester_no tanımlanmamış döneme göre yarıyıl bilgisi alınacak", $this->logContext([
                    "filters" => $filters,
                    "schedules" => $schedules,
                    "schedule" => $schedule
                ]));
                $currentSemesters = getSemesterNumbers($filters["semester"]);
                foreach ($currentSemesters as $currentSemester) {
                    $filters["semester_no"] = $currentSemester;
                    $result = $this->checkAndDeleteSchedule($schedule, $filters);
                    if (!empty($result))
                        $results[] = $result;
                }
            } else {
                $this->logger()->debug("semester_no tanımlı ", $this->logContext([
                    "filters" => $filters,
                    "schedules" => $schedules,
                    "schedule" => $schedule,
                ]));
                $result = $this->checkAndDeleteSchedule($schedule, $filters);
                if (!empty($result))
                    $results[] = $result;
            }
        }
        if (empty($results)) {
            $this->logger()->debug("Silme işlemi çalışmadı ", $this->logContext([
                "filters" => $filters,
            ]));
            return ["silme işlemi çalışmadı"];
        }
        $this->logger()->info("Program silme işlemi yapıldı", $this->logContext([
            "filters" => $filters,
            "results" => $results
        ]));
        return $results;
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek
     * @param Schedule $schedule 
     * @param $filters
     * @return array
     * @throws Exception
     */
    private function checkAndDeleteSchedule($schedule, $filters): array
    {
        $filters = $this->validator->validate($filters, "checkAndDeleteSchedule");
        //belirtilen günde bir ders var ise
        if (is_array($schedule->{"day" . $filters["day_index"]})) {
            if (key_exists("lesson_id", $schedule->{"day" . $filters["day_index"]})) {
                // lesson_id var ise tek bir ders var demektir
                if ($schedule->{"day" . $filters["day_index"]} == $filters['day']) {
                    //var olan gün ile belirtilen gün bilgisi aynı ise
                    $schedule->{"day" . $filters["day_index"]} = null; //gün boşaltıldı
                    if ($this->isScheduleEmpty($schedule)) {
                        $schedule->delete();
                        return ['deletedSchedule_id' => $schedule->id];
                    } else {
                        $this->updateSchedule($schedule);
                        return ['updatedSchedule_id' => $schedule->id];
                    }
                }
            } else {
                // Bu durumda günde iki ders var belirtilen verilere uyan silinecek
                $index = array_search($filters['day'], $schedule->{"day" . $filters["day_index"]});// dizide dersin indexsi bulunuyor.
                if ($index !== false) {
                    array_splice($schedule->{"day" . $filters["day_index"]}, $index, 1);
                }
                //eğer tek bir ders kaldıysa gün içerisindeki diziyi ders dizisi olarak ayarlar
                if (count($schedule->{"day" . $filters["day_index"]}) == 1) {
                    $schedule->{"day" . $filters["day_index"]} = $schedule->{"day" . $filters["day_index"]}[0];
                }
                if ($this->isScheduleEmpty($schedule)) {
                    $schedule->delete();
                    return ['deletedSchedule_id' => $schedule->id];
                } else {
                    $this->updateSchedule($schedule);
                    return ['updatedSchedule_id' => $schedule->id];
                }

            }
        } elseif (!is_null($schedule->{"day" . $filters["day_index"]})) {
            // bu durumda ders true yada false dir. Aslında false değerindedir
            $schedule->{"day" . $filters["day_index"]} = null;
            if ($this->isScheduleEmpty($schedule)) {
                $schedule->delete();
                return ['deletedSchedule_id' => $schedule->id];
            } else {
                $this->updateSchedule($schedule);
                return ['updatedSchedule_id' => $schedule->id];
            }
        } else {
            return [];//silinecek program bulunamadı
        }
        return ["koşullar sağlanmadı" => ["schedule" => $schedule, "filters" => $filters]];
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek buna artık gerek yok gibi 
     * Parametre olarak verilen programın günlerinin boş olup olmadığını döner
     * @param Schedule $schedule
     * @return bool
     */
    private function isScheduleEmpty(Schedule $schedule): bool
    {
        $weekEmpty = true;
        for ($i = 0; $i < 6; $i++) { //günler tek tek kontrol edilecek
            if (!is_null($schedule->{"day" . $i})) {
                $weekEmpty = false;
            }
        }
        return $weekEmpty;
    }

    /**
     * todo yeni tablo düzenine göre düzenlenecek Buna artık gerek yok 
     * Bir ders ile bağlantılı tüm Ders programlarının dizisini döener
     * @param $filter ["lesson_id","semester_no","semester","academic_year","type"] alanları olmalı
     * @return array
     * @throws Exception
     */
    public function findLessonSchedules($filter): array
    {
        /**
         * @var Lesson $lesson
         */
        $lesson = (new Lesson())->find($filter['lesson_id']) ?: throw new Exception("Ders bulunamadı");
        unset($filter["lesson_id"]);// ders alındıktan sonra sonraki işlemlerde sorun olmaması için lesson_id filtreden silinoyor.
        $filters = ["owner_type" => "lesson", "owner_id" => $lesson->id];
        // aynı bilgileri program sınıf ve hoca için de kaydedildiği için sadece ders için programlar alınıyor.
        $schedules = (new Schedule())->get()->where($filters)->all();
        /**
         * Derse ait ders programının filtrelerinin saklanacağı değişken
         */
        $schedule_filters = [];
        /**
         * @var Schedule $schedule
         */
        foreach ($schedules as $schedule) {
            $day_index = null;
            $day = null;
            $classroom = null;
            for ($i = 0; $i <= 5; $i++) {
                // Bir dersin program kaydından her saat için bir schedule kaydı var ve bunun içinde sadece bir günde bilgiler yazılı olabilir.
                if (!is_null($schedule->{"day$i"})) {
                    $day_index = $i;
                    $classroom = (new Classroom())->find($schedule->{"day$i"}['classroom_id']) ?: throw new Exception("Derslik Bulunamadı");
                    //todo gruplu dersler için gün seçimi doğru yapılmalı
                    $day = $schedule->{"day$i"};
                }
            }

            $owners = array_filter([
                "lesson" => $lesson->id ?? null,
                "user" => $lesson->lecturer_id ?? null,
                "program" => $lesson->program_id ?? null,
                "classroom" => $classroom?->id ?? null,
            ], function ($value) {
                return $value !== null && $value !== '';
            });
            foreach ($owners as $owner_type => $owner_id) {
                $schedule_filters[] = array_filter([
                    "owner_type" => $owner_type ?? null,
                    "owner_id" => $owner_id ?? null,
                    "semester" => $schedule->semester ?? null,
                    "academic_year" => $schedule->academic_year ?? null,
                    "semester_no" => $schedule->semester_no ?? null,
                    "type" => $schedule->type ?? null,
                    "time" => $schedule->time ?? null,
                    "day_index" => $day_index ?? null,
                    "day" => $day ?? null
                ], function ($value) {
                    return $value !== null && $value !== '';
                });
            }
        }
        return $schedule_filters;
    }
}