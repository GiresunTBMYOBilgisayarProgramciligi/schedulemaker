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
            'break' => $break
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
            foreach ($items as $item) {
                if ($this->checkOverlap($startTime->format('H:i'), $endTime->format('H:i'), $item->start_time, $item->end_time)) {
                    $isAvailable = false;
                    break;
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
        // Zamanları H:i formatına normalize et (veritabanından H:i:s gelebilir)
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

    /**
     * Itemleri kaydeder, çakışmaları kontrol eder ve 'preferred' çakışmalarını çözer
     * @param array $itemsData JSON decode edilmiş items dizisi
     * @return array
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): array
    {
        $this->database->beginTransaction();
        $createdIds = [];
        try {
            foreach ($itemsData as $itemData) {
                $this->logger()->debug("saveScheduleItems: Processing item", $this->logContext(['itemData' => $itemData]));
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

                // Tercih edilen alan çakışması yaşanan programların ID listesi
                $preferredConflictScheduleIds = [];

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
                                $preferredConflictScheduleIds[] = $relatedSchedule->id;
                            } else {
                                // preferred değilse standart conflict check (hata fırlatabilir)
                                $this->resolveConflict($itemData, $existingItem, $lesson);
                            }
                        }
                    }
                }

                // 2. Yeni Item'i Tüm İlgili Schedule'lara Kaydet
                foreach ($targetSchedules as $schedule) {
                    // Bu programa özel metadata kontrolü
                    $currentDetail = $itemData['detail'] ?? null;
                    if (in_array($schedule->id, $preferredConflictScheduleIds)) {
                        if (is_null($currentDetail)) {
                            $currentDetail = ['preferred' => true];
                        } elseif (is_array($currentDetail)) {
                            $currentDetail['preferred'] = true;
                        }
                    }

                    // Data formatını hazırla (Tekil array içinde array yapısı)
                    $validData = [];
                    if (isset($itemData['data']['lesson_id'])) {
                        $validData = [$itemData['data']];
                    } else {
                        $validData = $itemData['data'];
                    }

                    $validDetail = null;
                    if (!is_null($currentDetail)) {
                        if (!array_is_list($currentDetail)) {
                            $validDetail = [$currentDetail];
                        } else {
                            $validDetail = $currentDetail;
                        }
                    }

                    if ($itemData['status'] === 'group') {
                        // Group statusunde ise merge/split işlemi yap
                        // todo group işlemi sonucunda oluşan id'ler dönmeli
                        $groupIds = $this->processGroupItemSaving(
                            $schedule,
                            $itemData['day_index'],
                            $itemData['start_time'],
                            $itemData['end_time'],
                            $validData,
                            $validDetail
                        );
                        $createdIds = array_merge($createdIds, $groupIds);
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
                        $createdIds[] = $newItem->id;
                    }
                }
            }
            $this->database->commit();
            return $createdIds;
        } catch (Exception $e) {
            $this->database->rollBack();
            $this->logger()->error("Save Schedule Items Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'itemData' => $itemData ?? 'N/A'
            ]);
            throw $e;
        }
    }

    /**
     * Preferred item ile çakışma durumunda preferred item'i günceller (kısaltır veya böler)
     */
    private function resolvePreferredConflict(string $newStart, string $newEnd, ScheduleItem $preferredItem): void
    {
        // Zamanları H:i formatına normalize et
        $newStart = substr($newStart, 0, 5);
        $newEnd = substr($newEnd, 0, 5);
        $prefStart = substr($preferredItem->start_time, 0, 5);
        $prefEnd = substr($preferredItem->end_time, 0, 5);

        // Durum 1: Yeni item preferred item'i tamamen kapsıyor -> Sil
        if ($newStart <= $prefStart && $newEnd >= $prefEnd) {
            $preferredItem->delete();
            return;
        }

        // Durum 2: Yeni item son taraftan örtüşüyor (Örn: Pref 10-12, New 11-13 -> Pref 10-11)
        if ($newStart > $prefStart && $newStart < $prefEnd && $newEnd >= $prefEnd) {
            $preferredItem->end_time = $newStart;
            // Eğer süre sıfıra indiyse sil
            if (substr($preferredItem->start_time, 0, 5) >= substr($preferredItem->end_time, 0, 5)) {
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
            if (substr($preferredItem->start_time, 0, 5) >= substr($preferredItem->end_time, 0, 5)) {
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

    public function deleteScheduleItems(array $items): array
    {
        /** 
         * filter içerisinde gelen items listesi gerekli kontroller yapılarak silinecek:
         * 
         * gelen itemler aynı scheduleitem_id ye sahipse start ve end time bilgilerine göre birleştirilecekler. 
         * birleştirilen item schedule item ile aynı ise o item silinecek. Yani başlangıç ve bitiş saatleri tüm itemi kapsıyorsa silinecek.
         * tamamını kapsamıyorsa start ve end time bilgilerine göre schedule items tablosunda güncellenecek. 
         * item kaydetme işlemlerindeki prefered item ile çakışma durumunda yapılan işlemler gibibi silme işlem idüzenlenecek.
         * eğer silinmesi için gelen item ver olan itemin başlangıç kısmında ise start time güncellenecek son kısmında ise end time güncellenecek. orta kısmında ise item parçalanarak iki item olarak kaydedilecek.
         * Bu işlemler gelen schedule item ile bağlantılı tüm schedule'lar için yapılacak. schedule item kaydedilirken hangi schedule'lar kaydediliyorsa silme işlemi de hepsinde yapılacak. 
         * eğer slot gruplu ise silinen ders bilgisi schedule item içerisinde kontrol edilerek data içerisinden silinecek. eğer gerekiyorsa item parçalanacak. 
         */

        // $deletedIds: Silme işlemi başarıyla tamamlanan kayıtların ID listesi
        $deletedIds = [];
        // $errors: İşlem sırasında oluşan hata mesajlarının listesi
        $errors = [];

        // 1. Itemları ID'ye göre grupla
        // $groups: Frontend'den gelen parçalı verileri ID bazlı gruplar (her ID için birden fazla zaman dilimi olabilir)
        $groups = [];
        foreach ($items as $item) {
            // $id: İşlem yapılacak ScheduleItem'ın benzersiz kimliği
            $id = $item['schedule_item_id'] ?? ($item['id'] ?? null);
            if (!$id)
                continue;

            $model = new ScheduleItem();
            $model->fill($item);
            $groups[$id][] = $model;
        }

        // $chunkItems: Aynı ID'ye sahip silme isteklerinin listesi
        foreach ($groups as $id => $chunkItems) {
            try {
                // Ana itemi bul
                // $scheduleItem: Veritabanındaki gerçek ScheduleItem nesnesi
                $scheduleItem = (new ScheduleItem())->find($id);
                if (!$scheduleItem) {
                    $errors[] = "Item ID $id bulunamadı.";
                    continue;
                }

                // 2. Silinecek zaman aralıklarını hesapla (Union of selected slots)
                // $deleteIntervals: Bu item için silinecek tüm zaman aralıklarını (başlangıç-bitiş) tutan dizi
                $deleteIntervals = [];
                // $targetLessonIds: Sadece belirli dersler silinecekse, o derslerin ID listesi (Grup dersleri için önemli)
                $targetLessonIds = [];

                // $cItem: Gruptaki her bir parça isteği (Model nesnesi)
                foreach ($chunkItems as $cItem) {
                    // İsteğin içindeki ders verisinden hedef ders ID'lerini topla
                    foreach ($cItem->getSlotDatas() as $sd) {
                        if ($sd->lesson) {
                            $targetLessonIds[] = $sd->lesson->id;
                        }
                    }

                    // $startTimeStr: Silinecek aralığın başlangıç saati (string)
                    $startTimeStr = $cItem->start_time;
                    if (empty($startTimeStr))
                        continue;

                    $start = $startTimeStr; // Örn: "09:00"
                    // $type: Ders tipi (lesson veya exam) - Süre hesaplaması için gerekli
                    // Sınav kontrolü start_time null ise modelden de gelmeyebilir, item array'inden bakabiliriz ama cItem->status üzerinden de gidilebilir. 
                    // Ancak type bilgisi cItem içerisinde olmayabilir, bu yüzden fill() ile gelmemişse varsayılan 'lesson' diyelim.
                    $type = 'lesson'; // Varsayılan

                    // Ayarlardan süre bilgisini al
                    // $duration: Dersin veya sınavın süresi (dakika)
                    $duration = getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);

                    // Bitiş saatini hesapla
                    // $endUnix: Bitiş zamanının Unix timestamp değeri
                    $endUnix = strtotime($start) + ($duration * 60);
                    // $end: Bitiş zamanının saat formatı (H:i)
                    $end = date("H:i", $endUnix);

                    $deleteIntervals[] = ['start' => $start, 'end' => $end];
                }

                if (empty($deleteIntervals))
                    continue;

                // 3. İlgili TÜM sibling schedule itemları bul (User, Classroom, Program programlarındaki kopyaları)
                // Sadece katı zaman eşitliği yerine, ders ID'si ve zaman örtüşmesine bakıyoruz.

                // Mevcut itemin ait olduğu schedule bilgisini al
                // $currentSchedule: İşlem yapılan 'Schedule' (Program) nesnesi. Dönem ve yıl bilgisi için gerekli.
                $currentSchedule = (new Schedule())->find($scheduleItem->schedule_id);
                if (!$currentSchedule)
                    continue;

                // $siblings: Bu item'in diğer programlardaki (Hoca, Sınıf vb.) yansımaları (kardeşleri)
                $siblings = [];
                if (!empty($targetLessonIds)) {
                    $this->logger()->info("Deleting sibling items", ['targetLessonIds' => $targetLessonIds, 'currentScheduleId' => $currentSchedule->id]);

                    // Hedef derslerin bulunduğu diğer programları bulacağız.
                    // Verileri getSlotDatas üzerinden alıyoruz.
                    // $slotDatas: Item'in içindeki derslerin detaylı nesne hali (ScheduleItem modelinden gelir)
                    $slotDatas = $scheduleItem->getSlotDatas();
                    $this->logger()->info("Slot datas retrieved", ['slotDatas' => $slotDatas]);

                    // $relatedScheduleFilters: İlişkili programları bulmak için kullanılacak sorgu filtreleri
                    $relatedScheduleFilters = [];

                    // Ortak filtreler
                    // $baseFilter: Dönem ve yıl gibi temel filtre kriterleri
                    $baseFilter = [
                        'semester' => $currentSchedule->semester,
                        'academic_year' => $currentSchedule->academic_year,
                        'type' => $currentSchedule->type // lesson, exam vs
                    ];

                    // $sd: Her bir slot data (ders/hoca/sınıf üçlüsü)
                    foreach ($slotDatas as $sd) {
                        if (!isset($sd->lesson) || !in_array($sd->lesson->id, $targetLessonIds)) {
                            $this->logger()->info("Skipping slot data", ['lesson_id' => $sd->lesson->id ?? 'null']);
                            continue;
                        }

                        // Hoca Programı
                        if ($sd->lecturer) {
                            $f = $baseFilter;
                            $f['owner_type'] = 'user';
                            $f['owner_id'] = $sd->lecturer->id;
                            $f['semester_no'] = null;
                            $relatedScheduleFilters[] = $f;
                        }
                        // Program Programı
                        if ($sd->lesson->program_id) {
                            $f = $baseFilter;
                            $f['owner_type'] = 'program';
                            $f['owner_id'] = $sd->lesson->program_id;
                            $f['semester_no'] = $sd->lesson->semester_no;
                            $relatedScheduleFilters[] = $f;
                        }
                        // Sınıf Programı
                        if ($sd->classroom) {
                            $f = $baseFilter;
                            $f['owner_type'] = 'classroom';
                            $f['owner_id'] = $sd->classroom->id;
                            $f['semester_no'] = null;
                            $relatedScheduleFilters[] = $f;
                        }
                        // Dersin Kendisi (Lesson Schedule)
                        $f = $baseFilter;
                        $f['owner_type'] = 'lesson';
                        $f['owner_id'] = $sd->lesson->id;
                        $f['semester_no'] = null;
                        $relatedScheduleFilters[] = $f;
                    }

                    $this->logger()->info("Related schedule filters built", ['count' => count($relatedScheduleFilters), 'filters' => $relatedScheduleFilters]);

                    // Benzersiz filtreleri oluştur (duplicate sorguları önle)
                    // $uniqueFilters: Tekrar eden filtrelerin temizlenmiş hali
                    $uniqueFilters = [];
                    foreach ($relatedScheduleFilters as $rf) {
                        $key = md5(json_encode($rf));
                        $uniqueFilters[$key] = $rf;
                    }

                    // İlgili Schedule'ları bul
                    // $relatedScheduleIds: İlişkili Schedule'ların ID listesi
                    $relatedScheduleIds = [];
                    foreach ($uniqueFilters as $rf) {
                        // $s: Bulunan Schedule nesnesi
                        $s = (new Schedule())->get()->where($rf)->first(); // firstOrCreate değil, sadece varsa.
                        if ($s) {
                            $relatedScheduleIds[] = $s->id;
                        }
                    }

                    $this->logger()->info("Related schedule IDs found", ['ids' => $relatedScheduleIds]);

                    if (!empty($relatedScheduleIds)) {
                        // Silme aralığının sınırlarını bul (en erken ve en geç)
                        // $minStart: Silinecek aralığın en başı
                        $minStart = $deleteIntervals[0]['start'];
                        // $maxEnd: Silinecek aralığın en sonu
                        $maxEnd = $deleteIntervals[0]['end'];
                        // $di: Her bir silme aralığı
                        foreach ($deleteIntervals as $di) {
                            if ($di['start'] < $minStart)
                                $minStart = $di['start'];
                            if ($di['end'] > $maxEnd)
                                $maxEnd = $di['end'];
                        }

                        $this->logger()->info("Search interval", ['minStart' => $minStart, 'maxEnd' => $maxEnd]);

                        // Bu schedule'larda gün ve saat örtüşmesi olan itemleri getir
                        // $candidateItems: Belirlenen schedule ve zaman aralığında bulunan aday itemlar
                        $candidateItems = (new ScheduleItem())->get()->where([
                            'schedule_id' => ['in' => array_unique($relatedScheduleIds)],
                            'day_index' => $scheduleItem->day_index
                        ])->all();

                        $this->logger()->info("Candidate items found", ['count' => count($candidateItems)]);

                        // $cand: Aday item
                        foreach ($candidateItems as $cand) {
                            if ($this->checkOverlap($minStart, $maxEnd, $cand->start_time, $cand->end_time)) {
                                $siblings[] = $cand;
                            }
                        }
                        $this->logger()->info("Siblings matching overlap", ['count' => count($siblings)]);
                    }
                } else {
                    // Lesson ID yoksa (örn sadece rezervasyon), eski usül strict match + schedule owner kontrolü yapılabilir
                    // Şimdilik boş geçiyoruz, senaryomuz ders silme üzerine.
                    // $strictSiblings: Tam zaman eşleşmesi ile bulunan kardeşler (Alternatif yöntem)
                    $strictSiblings = (new ScheduleItem())->get()->where([
                        'day_index' => $scheduleItem->day_index,
                        'start_time' => $scheduleItem->start_time,
                        'end_time' => $scheduleItem->end_time,
                    ])->all();
                    // Ancak tüm DB'yi tarayamayız. Lesson id siz veri pek yok. Geçiyoruz.
                }

                // $targets: Sonuç olarak işlem yapılacak tüm itemların (kardeşler dahil) listesi
                $targets = [];
                // Kendisini de ekle (eğer listede yoksa ki olmalı)
                // Siblings araması kendisini de bulmuş olabilir, kontrol et.
                // $foundSelf: Ana item listede bulundu mu bayrağı
                $foundSelf = false;
                foreach ($siblings as $s) {
                    if ($s->id == $scheduleItem->id)
                        $foundSelf = true;
                }
                if (!$foundSelf)
                    $siblings[] = $scheduleItem;

                // $processedIds: İşlenen item ID'lerini takip ederek mükerrer işlemi önleme
                $processedIds = [];
                $targetLessonIds = array_unique($targetLessonIds);

                // $sib: Kontrol edilen kardeş item
                foreach ($siblings as $sib) {
                    if (in_array($sib->id, $processedIds))
                        continue;

                    // Lesson ID kontrolü: data içinde bu derslerden HERHANGİ BİRİ var mı?
                    if (!empty($targetLessonIds)) {
                        $lessonFound = false;
                        foreach ($sib->getSlotDatas() as $sd) {
                            if ($sd->lesson && in_array($sd->lesson->id, $targetLessonIds)) {
                                $lessonFound = true;
                                break;
                            }
                        }
                        if (!$lessonFound)
                            continue;
                    }

                    $processedIds[] = $sib->id;
                    $targets[] = $sib;
                }

                // 4. Bulunan tüm itemlar için silme/bölme işlemini uygula
                // $targetItem: Üzerinde işlem yapılacak nihai item
                foreach ($targets as $targetItem) {
                    $this->processItemDeletion($targetItem, $deleteIntervals, $targetLessonIds);
                }

                $deletedIds[] = $id;

            } catch (Exception $e) {
                $this->logger()->error("Delete schedule item error: " . $e->getMessage());
                $errors[] = "ID $id işlem hatası: " . $e->getMessage();
            }
        }

        return [
            'status' => empty($errors) ? 'success' : 'warning',
            'deleted_ids' => $deletedIds,
            'errors' => $errors
        ];
    }

    /**
     * Verilen item üzerinde belirtilen aralıkları siler (Trim/Split/Delete)
     * Eğer targetLessonIds varsa, silme işlemi sadece o dersler için geçerli olur (Group slot desteği).
     * @param ScheduleItem $item
     * @param array $deleteIntervals [['start'=>'09:00', 'end'=>'09:50'], ...]
     * @param array $targetLessonIds
     * @throws Exception
     */
    private function processItemDeletion(ScheduleItem $item, array $deleteIntervals, array $targetLessonIds = []): void
    {
        // 1. Zaman çizelgesini belirle (H:i formatına normalize et)
        // $startStr: Item başlangıç saati
        $startStr = substr($item->start_time, 0, 5);
        // $endStr: Item bitiş saati
        $endStr = substr($item->end_time, 0, 5);

        // $points: Zaman çizelgesini oluşturacak kritik noktalar (başlangıçlar ve bitişler)
        $points = [$startStr, $endStr];
        // $del: Her bir silme aralığı
        foreach ($deleteIntervals as $del) {
            // $dStart: Silme başlangıcı
            $dStart = substr($del['start'], 0, 5);
            // $dEnd: Silme bitişi
            $dEnd = substr($del['end'], 0, 5);

            // Sadece item aralığına giren noktaları ekle
            if ($dStart > $startStr && $dStart < $endStr)
                $points[] = $dStart;
            if ($dEnd > $startStr && $dEnd < $endStr)
                $points[] = $dEnd;
        }
        $points = array_unique($points);
        sort($points);

        // $newSegments: Silme işlemi sonrası oluşacak yeni (parçalanmış veya kısalmış) item parçaları
        $newSegments = [];
        // $itemData: Item'in mevcut veri içeriği (Dersler, hocalar vb.)
        // $originalLessons: Item içindeki derslerin listesi. Model fill edildiği için data dizidir.
        $originalLessons = isset($item->data['lesson_id']) ? [$item->data] : ($item->data ?? []);

        // Zaman dilimleri üzerinde iterasyon
        for ($i = 0; $i < count($points) - 1; $i++) {
            // $pStart: Şu anki dilimin başlangıcı
            $pStart = $points[$i];
            // $pEnd: Şu anki dilimin bitişi
            $pEnd = $points[$i + 1];

            // 0 süre kontrolü
            if ($pStart >= $pEnd)
                continue;

            // Bu aralık silinecek bir aralık mı?
            // $shouldDeleteLesson: Bu dilimin silinmesi gerekiyor mu bayrağı
            $shouldDeleteLesson = false;
            foreach ($deleteIntervals as $del) {
                $dStart = substr($del['start'], 0, 5);
                $dEnd = substr($del['end'], 0, 5);

                if ($dStart <= $pStart && $dEnd >= $pEnd) {
                    $shouldDeleteLesson = true;
                    break;
                }
            }

            // $currentSegmentLessons: Bu dilimde kalacak olan dersler
            $currentSegmentLessons = $originalLessons;
            if ($shouldDeleteLesson) {
                if (!empty($targetLessonIds)) {
                    // Sadece belirli dersleri sil
                    $currentSegmentLessons = array_values(array_filter($originalLessons, function ($l) use ($targetLessonIds) {
                        return !in_array($l['lesson_id'], $targetLessonIds);
                    }));
                } else {
                    // Tüm itemi sil
                    $currentSegmentLessons = [];
                }
            }

            if (!empty($currentSegmentLessons)) {
                // Segmenti ekle (Optimization: Önceki ile aynıysa birleştir)

                // Minimum süre kontrolü (Ghost item önleme)
                // $segStart: Segment başlangıç DateTime nesnesi
                $segStart = \DateTime::createFromFormat('H:i', $pStart);
                // $segEnd: Segment bitiş DateTime nesnesi
                $segEnd = \DateTime::createFromFormat('H:i', $pEnd);

                if ($segStart && $segEnd) {
                    // $diff: Süre farkı (saniye)
                    $diff = $segEnd->getTimestamp() - $segStart->getTimestamp();
                    if ($diff < 20 * 60) { // 20 dakikadan kısa ise (teneffüs vs) oluşturma
                        continue;
                    }
                }

                // $lastIdx: Son eklenen segmentin indeksi
                $lastIdx = count($newSegments) - 1;
                if ($lastIdx >= 0 && serialize($newSegments[$lastIdx]['lessons']) === serialize($currentSegmentLessons)) {
                    // Dersler aynıysa süreyi uzat (birleştir)
                    $newSegments[$lastIdx]['end'] = $pEnd;
                } else {
                    // Yeni segment ekle
                    $newSegments[] = ['start' => $pStart, 'end' => $pEnd, 'lessons' => $currentSegmentLessons];
                }
            }
        }

        // 2. Veri tabanı işlemleri
        if (empty($newSegments)) {
            // Hiç segment kalmadıysa item silinir
            $item->delete();
        } else {
            // İlk segment ile mevcut itemi güncelle
            // $first: İlk segment
            $first = array_shift($newSegments);
            $item->start_time = $first['start'];
            $item->end_time = $first['end'];
            $item->data = $first['lessons'];
            // Eğer tek ders kaldıysa status 'single' olabilir
            if (count($first['lessons']) === 1) {
                $item->status = 'single';
            } elseif (count($first['lessons']) > 1) {
                $item->status = 'group'; // Status güncellemesini unutma
            }

            // Model üzerinden güncelleme yaparak serialize işleminin düzgün olmasını sağla
            $item->update();

            // Kalanlar için yeni item oluştur
            // $seg: Geriye kalan diğer segmentler
            foreach ($newSegments as $seg) {
                // $newItem: Yeni oluşturulacak parça item
                $newItem = new ScheduleItem();
                $newItem->schedule_id = $item->schedule_id;
                $newItem->day_index = $item->day_index;
                $newItem->week_index = $item->week_index;
                $newItem->start_time = $seg['start'];
                $newItem->end_time = $seg['end'];
                $newItem->status = count($seg['lessons']) > 1 ? 'group' : 'single';
                $newItem->data = $seg['lessons'];
                $newItem->detail = $item->detail;
                $newItem->create();
            }
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
            return $this->checkOverlap($startTime, $endTime, $item->start_time, $item->end_time);
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
        $points = [$startTime, $endTime];
        foreach ($involvedItems as $item) {
            $points[] = $item->start_time;
            $points[] = $item->end_time;
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
                if ($item->start_time <= $pStart && $item->end_time >= $pEnd) {
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
}