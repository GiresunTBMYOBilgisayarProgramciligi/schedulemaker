<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ScheduleService;
use Exception;
use function App\Helpers\find_key_starting_with;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedules';//todo artık tek tablo değil bu tablo adı nerelerde kullanılacak? 
    protected string $modelName = "App\Models\Schedule";

    /********************************
     * Yardımcı ve Hazırlık Metodları
     ********************************/
    public function __construct()
    {
        parent::__construct();
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
     * todo yeni tablo düzenine göre düzenlenecek
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmuş bir dizi döner
     * @param array $filters
     * @return array|bool
     * @throws Exception
     */
    public function createScheduleExcelTable(array $filters = []): array|bool
    {
        $filters = (new \App\Helpers\FilterValidator())->validate($filters, "createScheduleExcelTable");

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
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    private function prepareScheduleCard($filters, bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        //$this->logger()->debug("Prepare Schedule Card için Filter alındı", ['filters' => $filters]);
        $filters = (new \App\Helpers\FilterValidator())->validate($filters, "prepareScheduleCard");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır (Genel Program)
        if (in_array($filters['owner_type'], ['user', 'classroom', 'lesson'])) {
            $filters['semester_no'] = null;
        }

        $schedule = (new Schedule())->firstOrCreate($filters);
        $availableLessons = ($only_table) ? [] : (new AvailabilityService())->availableLessons($schedule, $preference_mode);
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
            'lesson' => (new Lesson())->find($filters['owner_id'])->getFullName(true),
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
        $filters = (new \App\Helpers\FilterValidator())->validate($filters, "prepareScheduleCard");

        // Hoca, Derslik ve Ders programları dönemden bağımsızdır
        if (in_array($filters['owner_type'] ?? '', ['user', 'classroom', 'lesson'])) {
            $filters['semester_no'] = null;
        }

        $availableLessons = [];
        $schedule = null;

        $schedule = (new Schedule())->firstOrCreate($filters);
        $availableLessons = (new AvailabilityService())->availableLessons($schedule, $preference_mode);

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
        $filters = (new \App\Helpers\FilterValidator())->validate($filters, "getSchedulesHTML");
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


}
