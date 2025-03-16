<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\getCurrentSemester;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSetting;
use function App\Helpers\isAuthorized;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedule';
    protected string $modelName = "App\Models\Schedule";

    /**
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır.
     * @param $type string  html | excel
     * @param int $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     */
    private function generateEmptyWeek(string $type = 'html', int $maxDayIndex = 4): array
    {
        $emptyWeek = [];
        foreach (range(0, $maxDayIndex) as $index) {
            $emptyWeek["day{$index}"] = null;
            if ($type == 'excel')
                $emptyWeek["classroom{$index}"] = null;
        }
        return $emptyWeek;
    }

    /**
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmış bir dizi döner
     * @param array $filters
     * @return array|bool
     * @throws Exception
     */
    public function createScheduleExcelTable(array $filters = []): array|bool
    {
        $schedules = (new Schedule())->get()->where($filters)->all();
        if (count($schedules) == 0) return false; //program boş ise false dön
        $scheduleRows = $this->prepareScheduleRows($filters, 'excel');
        $scheduleArray = [];
        $scheduleArray[] = ['', 'Pazartesi', 'S', 'Salı', 'S', 'Çarşamba', 'S', 'Perşembe', 'S', 'Cuma', 'S'];

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
     * @throws Exception
     */
    private function prepareScheduleRows(array $filters = [], $type = "html"): array
    {
        $schedules = (new Schedule())->get()->where($filters)->all();
        /**
         * Boş tablo oluşturmak için tablo satır verileri
         */
        $scheduleRows = [
            "08.00 - 08.50" => $this->generateEmptyWeek($type),
            "09.00 - 09.50" => $this->generateEmptyWeek($type),
            "10.00 - 10.50" => $this->generateEmptyWeek($type),
            "11.00 - 11.50" => $this->generateEmptyWeek($type),
            "12.00 - 12.50" => $this->generateEmptyWeek($type),
            "13.00 - 13.50" => $this->generateEmptyWeek($type),
            "14.00 - 14.50" => $this->generateEmptyWeek($type),
            "15.00 - 15.50" => $this->generateEmptyWeek($type),
            "16.00 - 16.50" => $this->generateEmptyWeek($type)
        ];

        /**
         * Dersin saatlari ayrı ayrı eklendiği için ve her ders parçasının ayrı bir id değerinin olması için dersin saat sayısı bilgisini tutar
         */
        $lessonHourCount = [];
        /*
         * Veri tabanından alınan bilgileri tablo satırları yerine yerleştiriliyor
         */
        foreach ($schedules as $schedule) {
            $week = $schedule->getWeek($type);
            foreach ($week as $day => $value) {
                if (!is_null($value)) {
                    $scheduleRows[$schedule->time][$day] = $value;
                }
            }
        }
        return $scheduleRows;
    }

    /**
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmış bir HTML tablo döner
     * @param array $filters Where koşulunda kullanılmak üzere belirlenmiş alanlardan oluşan bir dizi
     * @return string
     * @throws Exception
     */
    public function createScheduleHTMLTable(array $filters = []): string
    {
        $scheduleRows = $this->prepareScheduleRows($filters, "html");
        // eğer semerser_not dizi ise dönemler birleştirilmiş demektir.
        $semester_no = (isset($filters['semester_no']) and !is_array($filters['semester_no'])) ? 'data-semester-no="' . $filters['semester_no'] . '"' : "";
        $semester = isset($filters['semester']) ? 'data-semester="' . $filters['semester'] . '"' : 'data-semester="' . getCurrentSemester() . '"';

        /**
         * Dersin saatlari ayrı ayrı eklendiği için ve her ders parçasının ayrı bir id değerinin olması için dersin saat sayısı bilgisini tutar
         */
        $lessonHourCount = [];
        $out =
            '
            <table class="table table-bordered table-sm small" ' . $semester_no . ' ' . $semester . '>
                                <thead>
                                <tr>
                                    <th style="width: 7%;">#</th>
                                    <th>Pazartesi</th>
                                    <th>Salı</th>
                                    <th>Çarşamba</th>
                                    <th>Perşembe</th>
                                    <th>Cuma</th>
                                    <!--<th>Cumartesi</th>-->
                                </tr>
                                </thead>
                                <tbody>';
        $times = array_keys($scheduleRows);
        for ($i = 0; $i < count($times); $i++) {
            $tableRow = $scheduleRows[$times[$i]];
            $out .=
                '
                <tr>
                    <td>
                    ' . $times[$i] . '
                    </td>';
            $dayIndex = 0;
            foreach ($tableRow as $day) {
                /*
                 * Eğer bir ders kaydedilmişse day true yada false değildir. Dizi olarak ders sınıf ve hoca bilgisini tutar
                 */
                if (is_array($day)) {
                    if (is_array($day[0])) {
                        //gün içerisinde iki ders var
                        $out .= '<td class="drop-zone">';
                        foreach ($day as $column) {
                            $column = (object)$column; // Array'i objeye dönüştür
                            $lesson = (new Lesson())->find($column->lesson_id);
                            $lessonHourCount[$lesson->id] = is_null($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                            $lecturerName = $lesson->getLecturer()->getFullName();
                            $classroomName = (new Classroom())->find($column->classroom_id)->name;
                            $out .= '
                            <div 
                            id="scheduleTable-lesson-' . $column->lesson_id . '-' . $lessonHourCount[$lesson->id] . '"
                            draggable="true" 
                            class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                            data-lesson-code="' . $lesson->code . '" data-semester-no="' . $lesson->semester_no . '" data-lesson-id="' . $lesson->id . '"
                            data-schedule-time="' . $times[$i] . '"
                            data-schedule-day="' . $dayIndex . '"
                            data-semester="' . $semester . '">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold" id="lecturer-' . $column->lecturer_id . '">
                                        <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/lesson/' . $lesson->id . '\">
                                            <i class="bi bi-book"></i> 
                                        </a>
                                        ' . $lesson->getFullName() . '
                                    </div>
                                    <div><a class="link-light link-underline-opacity-0" target="_blank" href="/admin/profile/' . $lesson->getLecturer()->id . '\">
                                        <i class="bi bi-person-square"></i>
                                    </a>
                                    ' . $lecturerName . '</div>
                                </div>
                                <a href="/admin/classroom/' . $column->classroom_id . '" class="link-light link-underline-opacity-0" target="_blank">
                                    <span  id="classroom-' . $column->classroom_id . '" class="badge bg-info rounded-pill">
                                        <i class="bi bi-door-open"></i> ' . $classroomName . '
                                    </span>
                                </a>
                            </div>';
                        }
                        $out .= '</td>';
                    } else {
                        // Eğer day bir array ise bilgileri yazdır
                        $day = (object)$day; // Array'i objeye dönüştür
                        $lesson = (new Lesson())->find($day->lesson_id);
                        $lessonHourCount[$lesson->id] = is_null($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                        $lecturerName = $lesson->getLecturer()->getFullName();
                        $classroomName = (new Classroom)->find($day->classroom_id)->name;
                        $out .= '
                        <td class="drop-zone">
                            <div 
                            id="scheduleTable-lesson-' . $day->lesson_id . '-' . $lessonHourCount[$lesson->id] . '"
                            draggable="true" 
                            class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                            data-lesson-code="' . $lesson->code . '" data-semester-no="' . $lesson->semester_no . '" data-lesson-id="' . $lesson->id . '"
                            data-schedule-time="' . $times[$i] . '"
                            data-schedule-day="' . $dayIndex . '"
                            data-semester="' . $semester . '">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold" id="lecturer-' . $day->lecturer_id . '">
                                    <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/lesson/' . $lesson->id . '\">
                                        <i class="bi bi-book"></i>
                                    </a> 
                                    ' . $lesson->getFullName() . '
                                        
                                    </div>
                                    <div>
                                    <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/profile/' . $day->lecturer_id . '\">
                                        <i class="bi bi-person-square"></i>
                                    </a>
                                    ' . $lecturerName . '
                                    </div>
                                </div>
                                <a href="/admin/classroom/' . $day->classroom_id . '" class="link-light link-underline-opacity-0" target="_blank">
                                    <span id="classroom-' . $day->classroom_id . '" class="badge bg-info rounded-pill">
                                        <i class="bi bi-door-open"></i> ' . $classroomName . '
                                    </span>
                                </a>
                            </div>
                        </td>';
                    }
                } elseif (is_null($day)) {
                    // Eğer null veya true ise boş dropzone ekle
                    $out .= ($times[$i] === "12.00 - 12.50")
                        ? '<td class="bg-danger"></td>' // Öğle saatinde kırmızı hücre
                        : '<td class="drop-zone"></td>';
                } elseif ($day === true) {
                    $out .= '<td class="bg-success"></td>';
                } else {
                    // Eğer false ise kırmızı vurgulu hücre ekle
                    $out .= '<td class="bg-danger"></td>';
                }
                $dayIndex++;
            }
        }
        $out .= '</tbody>
               </table>';

        return $out;
    }

    /**
     * @param array $filters
     * @param bool $only_table
     * @return string
     * @throws Exception
     */
    public function getSchedulesHTML(array $filters = [], bool $only_table = false): string
    {
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSetting('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSetting("academic_year");
        }
        $HTMLOUT = '';
        if (key_exists("semester_no", $filters) and is_array($filters['semester_no'])) {
            // birleştirilmiş dönem
            $HTMLOUT .= $this->prepareScheduleCard($filters, $only_table);
        } else {
            $currentSemesters = getSemesterNumbers($filters["semester"]);
            foreach ($currentSemesters as $semester_no) {
                $filters['semester_no'] = $semester_no;
                $HTMLOUT .= $this->prepareScheduleCard($filters, $only_table);
            }
        }
        return $HTMLOUT;
    }

    /**
     * @throws Exception
     */
    private function prepareScheduleCard($filters, bool $only_table = false): string
    {
        //Semester No dizi ise dönemler birleştirilmiş demektir
        $semester_no = is_array($filters['semester_no']) ? "Ders Programı" : $filters['semester_no'] . " Yarıyıl Programı";

        $HTMLOUT = '
                <!--begin::Row Program Satırı-->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">' . $semester_no . '</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                        <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                        <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                        <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!--begin::Row-->
                                <div class="row">';
        if (!($only_table)) {
            $HTMLOUT .= $this->createAvailableLessonsHTML($filters);
        }
        $colCSS = $only_table ? 'col-md-12' : 'col-md-9';
        $dataSemesterNo = is_array($filters['semester_no']) ? "" : 'data-semester-no="' . $filters['semester_no'] . '"';
        $HTMLOUT .= '   <div class="schedule-table ' . $colCSS . '" ' . $dataSemesterNo . '>';
        $HTMLOUT .= $this->createScheduleHTMLTable($filters);
        $HTMLOUT .= '

                                    </div>
                                </div>
                                <!--end::Row-->
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Row-->
            ';
        return $HTMLOUT;
    }

    /**
     * Ders programı tamamlanmamış olan derslerin bilgilerini döner.
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableLessons(array $filters = []): array
    {
        $available_lessons = [];
        if (key_exists('owner_type', $filters) and key_exists('owner_id', $filters)) {
            if (!key_exists("semester", $filters)) {
                $filters['semester'] = getSetting('semester');
            }
            if (!key_exists("academic_year", $filters)) {
                $filters['academic_year'] = getSetting("academic_year");
            }
            if ($filters['owner_type'] == "program") {
                $lessonFilters = [];
                if (array_key_exists("semester_no", $filters)) {//todo her zaman olması gerekmiyor mu?
                    $lessonFilters['semester_no'] = $filters['semester_no'];
                } else {
                    throw new Exception("Yarıyıl bilgisi yok");
                }
                $lessonFilters = array_merge($lessonFilters, [
                    'program_id' => $filters['owner_id'],
                    'semester' => $filters['semester'],
                    'academic_year' => $filters['academic_year'],
                    '!type' => 4// staj dersleri dahil değil
                ]);
                $lessonsList = (new Lesson())->get()->where($lessonFilters)->all();
                /**
                 * Programa ait tüm derslerin program tamamlanma durumları kontrol ediliyor.
                 * @var Lesson $lesson Model allmetodu sonucu oluşan sınıfı PHP strom tanımıyor. otomatik tamamlama olması için ekliyorum
                 */
                foreach ($lessonsList as $lesson) {
                    if (!$lesson->IsScheduleComplete()) {
                        //Ders Programı tamamlanmamışsa
                        $lesson->lecturer_name = $lesson->getLecturer()->getFullName(); // ders sınıfına Hoca adı ekleniyor
                        $lesson->lecturer_id = $lesson->getLecturer()->id;
                        $lesson->hours -= $this->getCount([
                            'owner_type' => 'lesson',
                            'owner_id' => $lesson->id,
                            "semester" => $filters['semester'],
                            "academic_year" => $filters['academic_year'],
                        ]);// programa eklenmiş olan saatlar çıkartılıyor.
                        $available_lessons[] = $lesson;
                    }
                }
            }
        } else {
            throw new Exception("Owner_type ve/veya owner id yok");
        }
        return $available_lessons;
    }

    /**
     * @throws Exception
     */
    public function createAvailableLessonsHTML(array $filters = []): string
    {
        if (!key_exists('semester_no', $filters)) {
            throw new Exception("Dönem numarası belirtilmelidir");
        }
        $HTMLOut = '<div class="available-schedule-items col-md-3 drop-zone small"
                                         data-semester-no="' . $filters['semester_no'] . '"
                                         style="max-height: 90vh;overflow: auto;"
                                         data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin">';
        $availableLessons = $this->availableLessons($filters);
        foreach ($availableLessons as $lesson) {
            $HTMLOut .= "
                    <div id=\"available-lesson-$lesson->id\" draggable=\"true\" 
                  class=\"d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary\"
                  data-semester-no=\"$lesson->semester_no\"
                  data-lesson-code=\"$lesson->code\"
                  data-lesson-id=\"$lesson->id\">
                    <div class=\"ms-2 me-auto\">
                      <div class=\"fw-bold\"><a class='link-light link-underline-opacity-0' target='_blank' href='/admin/lesson/$lesson->id'><i class=\"bi bi-book\"></i></a> $lesson->code $lesson->name ($lesson->size)</div>
                      <a class=\"link-light link-underline-opacity-0\" target='_blank' href=\"/admin/profile/$lesson->lecturer_id\"><i class=\"bi bi-person-square\"></i></a> $lesson->lecturer_name
                    </div>
                    <span class=\"badge bg-info rounded-pill\">$lesson->hours</span>
                  </div>
                    ";
        }
        $HTMLOut .= '</div>';
        return $HTMLOut;
    }

    /**
     * Başlangıç saatine ve ders saat miktarına göre saat dizisi oluşturur
     * @param string $startTimeRange Dersin ilk saat aralığı Örn. 08.00 - 08.50
     * @param int $hours
     * @return array
     */
    public function generateTimesArrayFromText(string $startTimeRange, int $hours): array
    {
        $schedule = [];

        // Başlangıç ve bitiş saatlerini ayır
        [$start, $end] = explode(" - ", $startTimeRange);
        $startHour = (int)explode(".", $start)[0]; // Saat kısmını al

        for ($i = 0; $i < $hours; $i++) {
            // Eğer saat 12'ye geldiyse öğle arası için atla
            if ($startHour == 12) {
                $startHour = 13;
            }

            // Yeni başlangıç ve bitiş saatlerini oluştur
            $newStart = str_pad($startHour, 2, "0", STR_PAD_LEFT) . ".00";
            $newEnd = str_pad($startHour, 2, "0", STR_PAD_LEFT) . ".50";

            // Listeye ekle
            $schedule[] = "$newStart - $newEnd";

            // Saat bilgisi bir sonraki saat için güncellenir
            $startHour++;
        }

        return $schedule;
    }

    /**
     * Belirtilen filtrelere uygun dersliklerin listesini döndürür
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function availableClassrooms(array $filters = []): array
    {
        $classroomFilters = [];
        if (!key_exists("hours", $filters) or !key_exists("time", $filters)) {
            throw new Exception("Missing hours and time");
        }
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSetting('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSetting("academic_year");
        }
        if (key_exists("lesson_id", $filters)) {
            $lesson = (new Lesson())->find($filters['lesson_id']);
            unset($filters['lesson_id']);// sonraki sorgularda sorun çıkartmaması için lesson id siliniyor.
            if ($lesson->classroom_type != 4) // karma sınıf için tür filtresi ekleme
                $classroomFilters["type"] = $lesson->classroom_type;
        }
        $times = $this->generateTimesArrayFromText($filters["time"], $filters["hours"]);
        $unavailable_classroom_ids = [];
        if (array_key_exists('owner_type', $filters)) {
            if ($filters['owner_type'] == "classroom") {
                $classroomSchedules = $this->getListByFilters(
                    [
                        "time" => ['in' => $times],
                        "owner_type" => $filters['owner_type'],
                        "semester" => $filters['semester'],
                        "academic_year" => $filters['academic_year'],
                    ]
                );
                foreach ($classroomSchedules as $classroomSchedule) {
                    if (!is_null($classroomSchedule->{$filters["day"]})) {// derslik programında belirtilen gün boş değilse derslik uygun değildir
                        // ID'yi anahtar olarak kullanarak otomatik olarak yinelemeyi önleriz
                        $unavailable_classroom_ids[$classroomSchedule->owner_id] = true;
                    }
                }
                // Anahtarları diziye dönüştürüyoruz.
                $unavailable_classroom_ids = array_keys($unavailable_classroom_ids);
                $classroomFilters["!id"] = ['in' => $unavailable_classroom_ids];
                $available_classrooms = (new ClassroomController())->getListByFilters($classroomFilters);
            } else {
                throw new Exception("owner_type classroom değil");
            }
        } else {
            throw new Exception("owner_type belirtilmemiş");
        }
        return $available_classrooms;
    }

    /**
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        if (!key_exists("lesson_hours", $filters) or !key_exists("time_start", $filters)) {
            throw new Exception("Ders saati yada program saati yok");
        }
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSetting('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSetting("academic_year");
        }
        $times = $this->generateTimesArrayFromText($filters["time_start"], $filters["lesson_hours"]);
        if (array_key_exists('owners', $filters)) {
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
                    $ownerFilter["semester_no"] = $filters["semester_no"];
                }
                $schedules = (new Schedule())->get()->where($ownerFilter)->all();
                foreach ($schedules as $schedule) {
                    if ($schedule->{$filters["day"]}) {// belirtilen gün bilgisi null yada false değilse
                        if (is_array($schedule->{$filters["day"]})) {
                            if ($owner_type == "user") {
                                //eğer hocanın o saatte dersi varsa program eklenemez
                                throw new Exception("Hoca Programı uygun değil");
                            }
                            // belirtilen gün içerisinde bir veri varsa
                            /**
                             * var olan dersin kodu
                             */
                            $lesson = (new Lesson())->find($schedule->{$filters["day"]}['lesson_id']);
                            /**
                             * yeni eklenmek istenen dersin kodu
                             */
                            $newLesson = (new Lesson())->find($filters['owners']['lesson']);
                            /*
                             * ders kodlarının sonu .1 .2 gibi nokta ve bir sayı ile bitmiyorsa çakışma var demektir.
                             */
                            if (preg_match('/\.\d+$/', $lesson->code) !== 1) {
                                //var olan ders gruplı değil
                                throw new Exception($lesson->name . "(" . $lesson->code . ") dersi ile çakışıyor");
                            } else {
                                // var olan ders gruplu
                                if (preg_match('/\.\d+$/', $newLesson->code) !== 1) {
                                    // yeni eklenecek olan ders gruplu değil
                                    throw new Exception("Gruplu bir dersin yanına sadece gruplu bir ders eklenebilir.");
                                }
                                //diğer durumda ekenecek olan ders de gruplu
                                // grup uygunluğu kontrolü javascript ile yapılıyor
                            }
                            $classroom = (new Classroom())->find($schedule->{$filters["day"]}['classroom_id']);
                            $newClassroom = (new Classroom())->find($filters['owners']['classroom']);
                            if ($classroom->name == $newClassroom->name) {
                                throw new Exception("Derslikler çakışıyor");
                            }
                        }
                    }
                }
            }

        } else {
            throw new Exception("Owners bilgileri girilmemiş");
        }
        return true;
    }

    /**
     * Schedule tablosuna yeni kayıt ekler
     * @param Schedule $new_schedule
     * @return int Son eklenen verinin id numarasını döner
     * @throws Exception
     */
    public function saveNew(Schedule $new_schedule): int
    {
        try {
            if (!isAuthorized("submanager", false, $new_schedule)) {
                throw new Exception("Ders Programı kaydetmek için yetkiniz yok");
            }

            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_schedule_arr = $new_schedule->getArray(['table_name', 'database', 'id']);
            //dizi türündeki veriler serialize ediliyor
            array_walk($new_schedule_arr, function (&$value) {
                if (is_array($value)) {
                    $value = serialize($value);
                }
            });
            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_schedule_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_schedule_arr);
            return $this->database->lastInsertId();
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası) program var gün güncellenecek
                $updatingSchedule = $this->getListByFilters($new_schedule->getArray(
                    ['table_name', 'database', 'id', 'day0', 'day1', 'day2', 'day3', 'day4', 'day5']))[0];
                // Yeni eklenecek gün bilgisinin hangi gün olduğunu bilmediğimden tüm günler için döngü oluşturulyor
                for ($i = 0; $i < 6; $i++) {
                    //yeni eklenecek dersin boş günlerini geçiyoruz. sadece dolu olanlar kaydedilecek
                    if (!is_null($new_schedule->{"day" . $i})) {
                        // yeni bilgi eklenecek alanın verisinin dizi olup olmadığına bakıyoruz. dizi ise bir ders vardır.
                        if (is_array($updatingSchedule->{"day" . $i})) {
                            /**
                             * Var olan dersin kodu
                             */
                            $lessonCode = (new Lesson())->find($updatingSchedule->{"day" . $i}['lesson_id'])->code;
                            /**
                             * Yeni eklenecek dersin kodu
                             */
                            $newLessonCode = (new Lesson())->find($new_schedule->{"day" . $i}['lesson_id'])->code;
                            // Derslerin ikisinin de kodunun son kısmında . ve bir sayı varsa gruplu bir derstir. Bu durumda aynı güne eklenebilir.
                            // grupların farklı olup olmadığının kontrolü javascript tarafında yapılıyor.
                            if (preg_match('/\.\d+$/', $lessonCode) === 1 and preg_match('/\.\d+$/', $newLessonCode) === 1) {
                                $dayData = [];
                                $dayData[] = $updatingSchedule->{"day" . $i};
                                $dayData[] = $new_schedule->{"day" . $i};

                                $updatingSchedule->{"day" . $i} = $dayData;
                            } else {
                                throw new Exception("Dersler gruplu değil bu şekilde kaydedilemez");
                            }
                        } else {
                            // Gün verisi dizi değilse null, true yada false olabilir.
                            if ($updatingSchedule->{"day" . $i} === false) {
                                throw new Exception("Belirtilen gün için ders eklenmesine izin verilmemiş");
                            } else {
                                // ders normal şekilde güncellenecek
                                $updatingSchedule->{"day" . $i} = $new_schedule->{"day" . $i};
                            }
                        }
                    }
                }
                return $this->updateSchedule($updatingSchedule);
            } else {
                throw new Exception($e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * @param Schedule $schedule
     * @return int
     * @throws Exception
     */
    public function updateSchedule(Schedule $schedule): int
    {
        try {
            if (!isAuthorized("submanager", false, $schedule)) {
                throw new Exception("Ders Programı güncelleme yetkiniz yok");
            }

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
     * @param $filters
     * @return void
     * @throws Exception
     */
    public function deleteSchedule($filters): void
    {
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSetting('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSetting("academic_year");
        }
        $scheduleData = array_diff_key($filters, array_flip(["day", "day_index", "classroom_name"]));// day ve day_index alanları çıkartılıyor
        if ($scheduleData['owner_type'] == "classroom") {
            $classroom = (new Classroom())->find($scheduleData['owner_id']);
            if ($classroom->type == 3) return; // uzaktan eğitim sınıfı ise programa kaydı yoktur
        }
        $schedules = $this->getListByFilters($scheduleData);

        if (!$schedules) {
            throw new Exception("Silinecek ders programı bulunamadı");
        }
        foreach ($schedules as $schedule) {
            if (!isAuthorized("submanager", false, $schedule)) {
                throw new Exception("Ders Programı güncelleme yetkiniz yok");
            }

            /**
             * Eğer dönem numarası belirtilmediyse aktif dönem numaralarınsaki tüm dönemler silinir.
             */
            if (!key_exists("semester_no", $filters)) {
                $currentSemesters = getSemesterNumbers($filters["semester"]);
                foreach ($currentSemesters as $currentSemester) {
                    $filters["semester_no"] = $currentSemester;
                    $this->checkAndDeleteSchedule($schedule, $filters);
                }
            } else {
                $this->checkAndDeleteSchedule($schedule, $filters);
            }
        }
    }

    /**
     * @param $schedule
     * @param $filters
     * @return void
     * @throws Exception
     */
    private function checkAndDeleteSchedule($schedule, $filters): void
    {
        //belirtilen günde bir ders var ise
        if (is_array($schedule->{"day" . $filters["day_index"]})) {
            if (key_exists("lesson_id", $schedule->{"day" . $filters["day_index"]})) {
                // lesson_id var ise tek bir ders var demektir
                if ($schedule->{"day" . $filters["day_index"]} == $filters['day']) {
                    //var olan gün ile belirtilen gün bilgisi aynı ise
                    $schedule->{"day" . $filters["day_index"]} = null; //gün boşaltıldı
                    if ($this->isScheduleEmpty($schedule))
                        $schedule->delete();
                    else
                        $this->updateSchedule($schedule);
                }
            } else {
                // Bu durumda günde iki ders var belirtilen verilere uyan silinecek
                for ($i = 0; $i < 2; $i++) {
                    if ($schedule->{"day" . $filters["day_index"]}[$i] == $filters['day']) {
                        array_splice($schedule->{"day" . $filters["day_index"]}, $i, 1);
                    }
                }
                //eğer tek bir ders kaldıysa gün içerisindeki diziyi ders dizisi olarak ayarlar
                if (count($schedule->{"day" . $filters["day_index"]}) == 1) {
                    $schedule->{"day" . $filters["day_index"]} = $schedule->{"day" . $filters["day_index"]}[0];
                }
                if ($this->isScheduleEmpty($schedule))
                    $schedule->delete();
                else
                    $this->updateSchedule($schedule);
            }
        } elseif (!is_null($schedule->{"day" . $filters["day_index"]})) {
            // bu durumda ders true yada false dir. Aslında false değerindedir
            $schedule->{"day" . $filters["day_index"]} = null;
            if ($this->isScheduleEmpty($schedule))
                $schedule->delete();
            else
                $this->updateSchedule($schedule);
        }

    }

    /**
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
}