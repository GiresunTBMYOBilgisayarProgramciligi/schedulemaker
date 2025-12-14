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
            'semester_no' => $schedule->semester_no,
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
        // todo bu eklemeyi homeIndex de hoca ve derslik programlarını birleştirmek için ekledim
        if (key_exists("semester_no", $filters) and $filters['semester_no'] == 0) {
            $filters['semester_no'] = getSemesterNumbers($filters['semester']);
        }

        if (key_exists("semester_no", $filters) and is_array($filters['semester_no'])) {
            // birleştirilmiş dönem
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

    public function lessonHourToMinute($scheduleType, $hours) : int {
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
                'semester_no' => $schedule->semester_no,
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
     * todo yeni tablo düzenine göre düzenlenecek
     * Programa eklenmek isteyen ders için eklenecek tüm saatlerde çakışma kontrolü yapar
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        $filters = $this->validator->validate($filters, "checkScheduleCrash");
        $this->logger()->debug("Check Schedule Crash Filters: ", $this->logContext($filters));
        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Ders bulunamadı");
        /*
         * Filtrede hoca id bilgisi varsa dersin hocası ile farklı bir hoca olma durumu olduğundan onu ayrı işlemek gerekiyor. Ayrıca sınav programında hoca id değeri gözetmen id olarak kullanılacak
         */
        $lecturer = isset($filters['lecturer_id']) ? (new User())->find($filters['lecturer_id']) : $lesson->getLecturer();
        $classroom = (new Classroom())->find($filters['classroom_id']);
        // bağlı dersleri alıyoruz
        $lessons = (new Lesson())->get()->where(["parent_lesson_id" => $lesson->id])->all();
        //bağlı dersler listesine ana dersi ekliyoruz
        array_unshift($lessons, $lesson);

        foreach ($lessons as $child) {
            /*
             * Ders çakışmalarını kontrol etmek için kullanılacak olan filtreler
             */
            $filters = array_merge($filters, [
                //Hangi tür programların kontrol edileceğini belirler owner_type=>owner_id
                "owners" => [
                    "program" => $child->program_id,
                    "user" => $lecturer->id,
                    "lesson" => $child->id
                ],//sıralama yetki kontrolü için önemli
            ]);
            /**
             * Uzem Sınıfı değilse çakışma kontrolüne dersliği de ekle
             * Bu aynı zamanda Uzem derslerinin programının uzem sınıfına kaydedilmemesini sağlar. Bu sayede unique hatası da oluşmaz
             */
            if (!is_null($classroom) and $classroom->type != 3) {
                $filters['owners']['classroom'] = $classroom->id;
            }
        }
        $this->logger()->debug('Check Schedule Crash Filters2', $this->logContext($filters));
        $times = $this->generateTimesArrayFromText($filters["time"], $filters["lesson_hours"], $filters["type"]);

        foreach ($filters["owners"] as $owner_type => $owner_id) {
            $ownerFilter = [
                "time" => ['in' => $times],
                "owner_type" => $owner_type,
                "owner_id" => $owner_id,
                "type" => $filters['type'],
                "semester" => $filters['semester'],
                "academic_year" => $filters['academic_year'],
            ];
            if ($owner_type == "program") {
                // sadece program için dönem numarası ekleniyor. Diğerlerinde diğer dönemlerle de çakışma kontrol edilmeli
                $ownerFilter["semester_no"] = $lesson->semester_no;
            }
            $schedules = (new Schedule())->get()->where($ownerFilter)->all();
            $this->logger()->debug('Check Schedule Crash Schedules', $this->logContext($schedules));
            foreach ($schedules as $schedule) {
                if ($schedule->{"day" . $filters["day_index"]}) {// belirtilen gün bilgisi null yada false değilse
                    if (is_array($schedule->{"day" . $filters["day_index"]})) {
                        $dayData = $schedule->{"day" . $filters["day_index"]};
                        // Normalize to list of lessons
                        $existingLessonsList = (isset($dayData[0]) && is_array($dayData[0])) ? $dayData : [$dayData];

                        foreach ($existingLessonsList as $existingLessonData) {
                            if ($owner_type == "user") {
                                //eğer hocanın/gözetmenin o saatte dersi varsa program eklenemez
                                $msg = (in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam'])) ? "Aynı gözetmen aynı saatte birden fazla sınavda görev alamaz." : "Hoca Programı uygun değil";
                                throw new Exception($msg);
                            }

                            /**
                             * var olan ders
                             * @var Lesson $existingLesson
                             */
                            $existingLesson = (new Lesson())->find($existingLessonData['lesson_id']) ?: throw new Exception("Var olan ders bulunamadı");
                            /**
                             * yeni eklenmek istenen ders
                             * @var Lesson $newLesson
                             */
                            $newLesson = (new Lesson())->find($filters['owners']['lesson']) ?: throw new Exception("yeni ders bulunamadı");

                            if (in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                                // 1. Aynı ders kontrolü (Base code kontrolü)
                                $existingBase = preg_replace('/\.\d+$/', '', $existingLesson->code);
                                $newBase = preg_replace('/\.\d+$/', '', $newLesson->code);

                                if ($existingBase !== $newBase) {
                                    throw new Exception("Sınav programında aynı saate farklı dersler konulamaz.");
                                }

                                // 2. Farklı Derslik Kontrolü
                                if (isset($filters['owners']['classroom']) && $existingLessonData['classroom_id'] == $filters['owners']['classroom']) {
                                    throw new Exception("Aynı derslikte aynı saatte birden fazla sınav olamaz.");
                                }

                                // 3. Farklı Gözetmen Kontrolü (User owner_type kontrolü yukarıda yapıldı ama program/derslik kontrolünde de bakılmalı mı? 
                                // Hayır, çünkü gözetmen çakışması owner_type='user' döngüsünde yakalanır. 
                                // Ancak burada mevcut dersin gözetmeni ile yeni dersin gözetmeni aynı mı diye bakmıyoruz, 
                                // çünkü owner_type='user' değilse (örn program) mevcut dersin gözetmeni başkası olabilir.
                                // Fakat biz yeni ders için atanan gözetmeni kontrol ediyoruz.
                                // Eğer owner_type='program' ise ve mevcut dersin gözetmeni X ise, ve biz Y atıyorsak sorun yok.
                                // Eğer X atıyorsak, X'in programı owner_type='user' da kontrol edilecek.
                            } else {
                                // Ders Programı Kuralları
                                /*
                                 * ders kodlarının sonu .1 .2 gibi nokta ve bir sayı ile bitmiyorsa çakışma var demektir.
                                 */
                                if (preg_match('/\.\d+$/', $existingLesson->code) !== 1) {
                                    //var olan ders gruplı değil
                                    throw new Exception($existingLesson->name . "(" . $existingLesson->code . ") dersi ile çakışıyor");
                                } else {
                                    // var olan ders gruplu
                                    if (preg_match('/\.\d+$/', $newLesson->code) !== 1) {
                                        // yeni eklenecek olan ders gruplu değil
                                        throw new Exception($existingLesson->getFullName() . " dersinin yanına sadece gruplu bir ders eklenebilir.");
                                    }
                                    //diğer durumda ekenecek olan ders de gruplu
                                    // grup uygunluğu kontrolü javascript ile yapılıyor
                                }

                                if (isset($filters['owners']['classroom'])) {
                                    $existingClassroom = (new Classroom())->find($existingLessonData['classroom_id']) ?: throw new Exception("Derslik Bulunamadı");
                                    $newClassroom = (new Classroom())->find($filters['owners']['classroom']) ?: throw new Exception("Derslik Bulunamadı");
                                    if ($existingClassroom->name == $newClassroom->name) {
                                        throw new Exception("Derslikler çakışıyor");
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
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