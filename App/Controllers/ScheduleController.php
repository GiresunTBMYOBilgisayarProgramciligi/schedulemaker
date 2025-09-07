<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\User;
use Exception;
use PDOException;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedule';
    protected string $modelName = "App\Models\Schedule";

    /**
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır.
     * @param $type string  html | excel
     * @param int|null $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     * @throws Exception
     */
    private function generateEmptyWeek(string $type = 'html', int $maxDayIndex = null): array
    {
        $maxDayIndex = $maxDayIndex ?? getSettingValue('maxDayIndex', default: 4);
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
        if (count($schedules) == 0) return false; // program boş ise false dön

        $scheduleRows = $this->prepareScheduleRows($filters, 'excel');
        $scheduleArray = [];

        // Günler dinamik olarak oluşturuluyor
        $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
        $headerRow = ['']; // ilk hücre boş olacak (saat için)

        for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {
            $headerRow[] = $days[$i]; // Gün adı
            $headerRow[] = 'S';       // Sütun başlığı (Senin tablodaki "S")
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
     * @throws Exception
     */
    private function prepareScheduleRows(array $filters = [], $type = "html", $maxDayIndex = null): array
    {
        $maxDayIndex = $maxDayIndex ?? getSettingValue('maxDayIndex', default: 4);
        $schedules = (new Schedule())->get()->where($filters)->all();
        /**
         * Boş tablo oluşturmak için tablo satır verileri
         */
        $scheduleRows = [
            "08.00 - 08.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "09.00 - 09.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "10.00 - 10.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "11.00 - 11.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "12.00 - 12.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "13.00 - 13.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "14.00 - 14.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "15.00 - 15.50" => $this->generateEmptyWeek($type, $maxDayIndex),
            "16.00 - 16.50" => $this->generateEmptyWeek($type, $maxDayIndex)
        ];


        /*
         * Veri tabanından alınan bilgileri tablo satırları yerine yerleştiriliyor
         */
        foreach ($schedules as $schedule) {
            $week = $schedule->getWeek($type);
            foreach ($week as $day => $value) {
                if (!is_null($value)) {
                    if ($value === true and is_array($scheduleRows[$schedule->time][$day])) {
                        // value değeri true ise hocanın tercih ettiği saat demek. aynı zamanda o saat bir dizi ise o saate atama yapılmış demek.
                        // bu durumda atama yapılmış dersin gözükmesi için saat bilgisi değiştirilmiyor.
                        continue;
                    }
                    $scheduleRows[$schedule->time][$day] = $value;
                }
            }
        }
        return $scheduleRows;
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
                $filters['semester'] = getSettingValue('semester');
            }
            if (!key_exists("academic_year", $filters)) {
                $filters['academic_year'] = getSettingValue("academic_year");
            }
            $lessonFilters = [];
            /**
             *uygun ders listesi program için hazırlanıyor.
             */
            if ($filters['owner_type'] == "program") {
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
            } elseif ($filters['owner_type'] == "classroom") {
                $classroom = (new Classroom())->find($filters['owner_id']);
                if (array_key_exists("semester_no", $filters)) {//todo her zaman olması gerekmiyor mu?
                    $lessonFilters['semester_no'] = $filters['semester_no'];
                } else {
                    throw new Exception("Yarıyıl bilgisi yok");
                }
                $lessonFilters = array_merge($lessonFilters, [
                    'classroom_type' => $classroom->type,
                    'semester' => $filters['semester'],
                    'academic_year' => $filters['academic_year'],
                    '!type' => 4// staj dersleri dahil değil
                ]);
            } elseif ($filters['owner_type'] == "user") {
                if (array_key_exists("semester_no", $filters)) {//todo her zaman olması gerekmiyor mu?
                    $lessonFilters['semester_no'] = $filters['semester_no'];
                } else {
                    throw new Exception("Yarıyıl bilgisi yok");
                }
                $lessonFilters = array_merge($lessonFilters, [
                    'lecturer_id' => $filters['owner_id'],
                    'semester' => $filters['semester'],
                    'academic_year' => $filters['academic_year'],
                    '!type' => 4// staj dersleri dahil değil
                ]);
            }
            $lessonsList = (new Lesson())->get()->where($lessonFilters)->all();
            /**
             * Programa ait tüm derslerin program tamamlanma durumları kontrol ediliyor.
             * @var Lesson $lesson Model allmetodu sonucu oluşan sınıfı PHP strom tanımıyor. otomatik tamamlama olması için ekliyorum
             */
            foreach ($lessonsList as $lesson) {
                if (!$lesson->IsScheduleComplete()) {
                    //Ders Programı tamamlanmamışsa
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
        } else {
            throw new Exception("Owner_type ve/veya owner id yok");
        }
        return $available_lessons;
    }

    /**
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmış bir HTML tablo döner
     * @param array $filters Where koşulunda kullanılmak üzere belirlenmiş alanlardan oluşan bir dizi
     * @return string
     * @throws Exception
     */
    public function createScheduleHTMLTable(array $filters = []): string
    {
        $createTableHeaders = function (): string {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = '<th style="width: 7%;">#</th>';
            for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {
                $headers .= '<th>' . $days[$i] . '</th>';
            }
            return $headers;
        };

        $scheduleRows = $this->prepareScheduleRows($filters, "html");
        // eğer semerser_no dizi ise dönemler birleştirilmiş demektir.
        $semester_no = (isset($filters['semester_no']) and !is_array($filters['semester_no'])) ? 'data-semester-no="' . $filters['semester_no'] . '"' : "";
        // eğer dönem belirtilmemişse aktif dönem bilgisi alınır
        $semester = isset($filters['semester']) ? 'data-semester="' . $filters['semester'] . '"' : 'data-semester="' . getSettingValue("semester") . '"';

        /**
         * Dersin saatlari ayrı ayrı eklendiği için ve her ders parçasının ayrı bir id değerinin olması için dersin saat sayısı bilgisini tutar
         */
        $lessonHourCount = [];
        //todo semester_no ve semester bilgisi card elementinde olacağı için burada olmasa da olur gibi
        $out =
            '
            <table class="table table-bordered table-sm small" ' . $semester_no . ' ' . $semester . '>
                                <thead>
                                <tr>' .
            $createTableHeaders()
            . '</tr>
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
                    if (isset($day[0]) and is_array($day[0])) {
                        //gün içerisinde iki ders var
                        $out .= '<td class="drop-zone">';
                        foreach ($day as $column) {
                            $column = (object)$column; // Array'i objeye dönüştür
                            $lesson = (new Lesson())->find($column->lesson_id) ?: throw new Exception("Ders bulunamdı");
                            $lessonHourCount[$lesson->id] = !isset($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                            $lecturer = (new User())->find($column->lecturer_id);
                            $classroom = (new Classroom())->find($column->classroom_id);
                            $draggable = "true";
                            if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
                                $draggable = "false";
                            }
                            $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
                            $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
                            $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . $parentLesson->getProgram()->name . ') dersine bağlı olduğu için düzenlenemez."';
                            /**
                             * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
                             */
                            $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . $lesson->getProgram()->name . ')' : $lesson->name;
                            $out .= '
                            <div 
                            id="scheduleTable-lesson-' . $column->lesson_id . '-' . $lessonHourCount[$lesson->id] . '"
                            draggable="' . $draggable . '" 
                            class="d-flex justify-content-between align-items-start mb-1 p-2 rounded ' . $text_bg . '"
                            data-lesson-code="' . $lesson->code . '" data-semester-no="' . $lesson->semester_no . '" data-lesson-id="' . $lesson->id . '" data-lecturer-id="' . $lecturer->id . '"
                            data-time="' . $times[$i] . '"
                            data-day-index="' . $dayIndex . '"
                            ' . $semester . '
                            ' . $popover . '
                            data-academic-year="' . $lesson->academic_year . '"
                            data-classroom-id="' . $classroom->id . '"
                            >
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold lesson-title" data-bs-toggle="tooltip" data-bs-placement="left" title="' . $lesson->code . '">
                                        <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/lesson/' . $lesson->id . '\">
                                            <i class="bi bi-book"></i> 
                                        </a>
                                        ' . $lessonName . '
                                    </div>
                                    <div class="text-nowrap lecturer-title" id="lecturer-' . $lecturer->id . '" >
                                        <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/profile/' . $lecturer->id . '\">
                                            <i class="bi bi-person-square"></i>
                                        </a>
                                        ' . $lecturer->getFullName() . '
                                    </div>
                                </div>
                                <a href="/admin/classroom/' . $classroom->id . '" class="link-light link-underline-opacity-0" target="_blank">
                                    <span  id="classroom-' . $classroom->id . '" class="badge bg-info rounded-pill">
                                        <i class="bi bi-door-open"></i> ' . $classroom->name . '
                                    </span>
                                </a>
                            </div>';
                        }
                        $out .= '</td>';
                    } else {
                        // Eğer day bir array ise bilgileri yazdır
                        $day = (object)$day; // Array'i objeye dönüştür
                        $lesson = (new Lesson())->find($day->lesson_id) ?: throw new Exception("Ders bulunamdı");
                        $lessonHourCount[$lesson->id] = !isset($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                        $lecturer = (new User())->find($day->lecturer_id);
                        $classroom = (new Classroom)->find($day->classroom_id);
                        $draggable = "true";
                        if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
                            $draggable = "false";
                        }
                        $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
                        $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
                        $badgeCSS = is_null($lesson->parent_lesson_id) ? "bg-info" : "bg-light text-dark";
                        $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . $parentLesson->getProgram()->name . ') dersine bağlı olduğu için düzenlenemez."';
                        /**
                         * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
                         */
                        $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . $lesson->getProgram()->name . ')' : $lesson->name;
                        $out .= '
                        <td class="drop-zone">
                            <div 
                            id="scheduleTable-lesson-' . $lesson->id . '-' . $lessonHourCount[$lesson->id] . '"
                            draggable="' . $draggable . '" 
                            class="d-flex justify-content-between align-items-start mb-1 p-2 rounded ' . $text_bg . '"
                            data-lesson-code="' . $lesson->code . '" data-semester-no="' . $lesson->semester_no . '" data-lesson-id="' . $lesson->id . '" data-lecturer-id="' . $lecturer->id . '"
                            data-time="' . $times[$i] . '"
                            data-day-index="' . $dayIndex . '"
                            ' . $semester . '
                            ' . $popover . '
                            data-academic-year="' . $lesson->academic_year . '"
                            data-classroom-id="' . $classroom->id . '"
                            >
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold lesson-title" data-bs-toggle="tooltip" data-bs-placement="left" title="' . $lesson->code . '">
                                        <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/lesson/' . $lesson->id . '\">
                                            <i class="bi bi-book"></i>
                                        </a> 
                                        ' . $lessonName . '
                                            
                                    </div>
                                    <div class="text-nowrap lecturer-title" id="lecturer-' . $lecturer->id . '">
                                        <a class="link-light link-underline-opacity-0" target="_blank" href="/admin/profile/' . $lecturer->id . '\">
                                            <i class="bi bi-person-square"></i>
                                        </a>
                                        ' . $lecturer->getFullName() . '
                                    </div>
                                </div>
                                <a href="/admin/classroom/' . $classroom->id . '" class="link-light link-underline-opacity-0" target="_blank">
                                    <span id="classroom-' . $classroom->id . '" class="badge ' . $badgeCSS . ' rounded-pill">
                                        <i class="bi bi-door-open"></i> ' . $classroom->name . '
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
     * AvailableLessons metodunun hazırladığı Ders programı için uygun derslerin html çıktısını hazırlar
     * @throws Exception
     * todo html çıktı hazırlayan fonksiyonlar kaldırılıp view içerisinde hazırlanmalı
     */
    public function createAvailableLessonsHTML(array $filters = []): string
    {
        if (!key_exists('semester_no', $filters)) {
            throw new Exception("Dönem numarası belirtilmelidir");
        }
        /*
         * Semester no dizi olarak gelmişse sınıflar birleştirilmiş demektir. Bu da Tekil sayfalarda kullanılıyor (Hoca,ders,derslik)
         */
        $semester_no = is_array($filters["semester_no"]) ? "" : $filters["semester_no"];
        // todo schedule card elementinde semester_no bilgisi olacak burada tekrar olmasına gerek var mı
        $HTMLOut = '<div class="row available-schedule-items drop-zone small"
                                         data-semester-no="' . $semester_no . '"
                                         data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin" data-bs-trigger="data-bs-placement="left"">';
        $availableLessons = $this->availableLessons($filters);
        foreach ($availableLessons as $lesson) {
            /**
             * @var Lesson $lesson
             * @var Lesson $parentLesson
             */
            $draggable = "true";
            if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
                $draggable = "false";
            }
            $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
            $badgeCSS = is_null($lesson->parent_lesson_id) ? "bg-info" : "bg-light text-dark";
            $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
            $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . $parentLesson->getProgram()->name . ') dersine bağlı olduğu için düzenlenemez."';
            /**
             * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
             */
            $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . $lesson->getProgram()->name . ')' : $lesson->name;
            $HTMLOut .= "
                    <div class='frame col-md-4 p-0 ps-1 '>
                        <div id=\"available-lesson-$lesson->id\" draggable=\"$draggable\" 
                          class=\"d-flex justify-content-between align-items-start mb-1 p-2 rounded $text_bg\"
                          data-semester-no=\"$lesson->semester_no\"
                          data-semester=\"$lesson->semester\"
                          data-academic-year=\"$lesson->academic_year\"
                          data-lesson-code=\"$lesson->code\"
                          data-lesson-id=\"$lesson->id\"
                          data-lecturer-id=\"" . $lesson->getLecturer()->id . "\"
                          $popover
                          data-lesson-hours=\"$lesson->hours\"
                        >
                            <div class=\"ms-2 me-auto\">
                              <div class=\"fw-bold lesson-title\" data-bs-toggle=\"tooltip\" data-bs-placement=\"left\" title=\" $lesson->code \">
                                <a class='link-light link-underline-opacity-0' target='_blank' href='/admin/lesson/$lesson->id'>
                                 <i class=\"bi bi-book\"></i>
                                </a> 
                                $lessonName
                              </div>
                              <div class=\"text-nowrap lecturer-title\" id=\"lecturer-$lesson->lecturer_id\">
                                <a class=\"link-light link-underline-opacity-0\" target='_blank' href=\"/admin/profile/$lesson->lecturer_id\">
                                <i class=\"bi bi-person-square\"></i>
                              </a>
                              " . $lesson->getLecturer()->getFullName() . "
                              </div>
                              
                            </div>
                            <span class=\"badge $badgeCSS rounded-pill\">$lesson->hours</span>
                      </div>
                  </div>
                    ";
        }
        $HTMLOut .= '</div><!--end::available-schedule-items-->';;
        return $HTMLOut;
    }

    /**
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    private function prepareScheduleCard($filters, bool $only_table = false): string
    {
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
        //todo setdataAttiribute fonksiyonu oluştur
        $HTMLOUT = '
                <!--begin::Row Program Satırı-->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card card-outline card-primary"
                        data-owner-type="' . $filters['owner_type'] . '"
                        data-owner-id="' . $filters['owner_id'] . '"
                        data-type="' . $filters['type'] . '"
                        data-academic-year="' . $filters['academic_year'] . '"
                        data-semester="' . $filters['semester'] . '"
                        ' . $dataSemesterNo . '
                        data-owner-name="' . $ownerName . '"
                        >
                            <div class="card-header">
                                <h3 class="card-title">' . $cardTitle . '</h3>
                                <div class="card-tools"><!-- todo butondan değil card dan bilgiler alınacak-->
                                    <button id="singlePageExport" data-owner-type="' . $filters["owner_type"] . '" data-owner-id="' . $filters["owner_id"] . '" type="button" class="btn btn-outline-primary btn-sm" >
                                        <span>Excel\'e aktar</span> 
                                    </button>
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
                            <div class="card-body">';
        if (!($only_table)) {
            $HTMLOUT .= $this->createAvailableLessonsHTML($filters);
        }
        $HTMLOUT .= '
                                <!--begin::Row Schedule Table-->
                                <div class="row">';
        $HTMLOUT .= '   <div class="schedule-table col-md-12" ' . $dataSemesterNo . '>'; //todo semester_no bilgisi card dan alınacak
        $HTMLOUT .= $this->createScheduleHTMLTable($filters);
        $HTMLOUT .= '

                                    </div><!--end::schedule-table-->
                                </div><!--end::Row-->
                            </div><!--end::card-body-->
                        </div><!--end::Card-->
                    </div>
                </div><!--end::Row-->
            ';
        return $HTMLOUT;
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
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSettingValue('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSettingValue("academic_year");
        }
        $HTMLOUT = '';
        // todo bueklemeyi homeIndex de hoca ve derslik programlarını birleştirmek için ekledim Bu işleme bir düzen getirilmeli
        if (key_exists("semester_no", $filters) and $filters['semester_no'] == "birleştir") {
            $filters['semester_no'] = ['in' => getSemesterNumbers($filters['semester'])];
        }

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
        //todo hoca programında derslikler geliyor ama Program programında gelmiyor
        $classroomFilters = [];
        if (!key_exists("hours", $filters) or !key_exists("time", $filters)) {
            throw new Exception("Missing hours and time");
        }
        if (!key_exists("semester", $filters)) {
            $filters['semester'] = getSettingValue('semester');
        }
        if (!key_exists("academic_year", $filters)) {
            $filters['academic_year'] = getSettingValue("academic_year");
        }
        if (key_exists("lesson_id", $filters)) {
            $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Derslik türünü belirlemek için ders bulunamadı");
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
     * Programa eklenmek isteyen ders için eklenecek tüm saatlerde çakışma kontrolü yapar
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        $filters = $this->checkFilters($filters, "checkScheduleCrash");
        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Ders bulunamadı");
        $lecturer = $lesson->getLecturer();
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

        $times = $this->generateTimesArrayFromText($filters["time"], $filters["lesson_hours"]);

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
            foreach ($schedules as $schedule) {
                if ($schedule->{"day" . $filters["day_index"]}) {// belirtilen gün bilgisi null yada false değilse
                    if (is_array($schedule->{"day" . $filters["day_index"]})) {
                        if ($owner_type == "user") {
                            //eğer hocanın o saatte dersi varsa program eklenemez
                            throw new Exception("Hoca Programı uygun değil");
                        }
                        // belirtilen gün içerisinde bir veri varsa
                        /**
                         * var olan ders
                         * @var Lesson $lesson
                         */
                        $lesson = (new Lesson())->find($schedule->{"day" . $filters["day_index"]}['lesson_id']) ?: throw new Exception("Var olan ders bulunamadı");
                        /**
                         * yeni eklenmek istenen ders
                         * @var Lesson $newLesson
                         */
                        $newLesson = (new Lesson())->find($filters['owners']['lesson']) ?: throw new Exception("yeni ders bulunamadı");
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
                                throw new Exception($lesson->getFullName() . " dersinin yanına sadece gruplu bir ders eklenebilir.");
                            }
                            //diğer durumda ekenecek olan ders de gruplu
                            // grup uygunluğu kontrolü javascript ile yapılıyor
                        }
                        $classroom = (new Classroom())->find($schedule->{"day" . $filters["day_index"]}['classroom_id']) ?: throw new Exception("Derslik Bulunamadı");
                        if (isset($filters['owners']['classroom'])) {
                            $newClassroom = (new Classroom())->find($filters['owners']['classroom']) ?: throw new Exception("Derslik Bulunamadı");
                            if ($classroom->name == $newClassroom->name) {
                                throw new Exception("Derslikler çakışıyor");
                            }
                        }
                    }
                }
            }
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
                            $lesson = (new Lesson())->find($updatingSchedule->{"day" . $i}['lesson_id']) ?: throw new Exception("Var olan ders bulunamadı");
                            /**
                             * Yeni eklenecek dersin kodu
                             */
                            $newLesson = (new Lesson())->find($new_schedule->{"day" . $i}['lesson_id']) ?: throw new Exception("Eklenecek ders ders bulunamadı");
                            // Derslerin ikisinin de kodunun son kısmında . ve bir sayı varsa gruplu bir derstir. Bu durumda aynı güne eklenebilir.
                            // grupların farklı olup olmadığının kontrolü javascript tarafında yapılıyor.
                            if (preg_match('/\.\d+$/', $lesson->code) === 1 and preg_match('/\.\d+$/', $newLesson->code) === 1) {
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
     * @param $filters array silinecek programın veri tabanında bulunması için gerekli veriler.
     * @return void
     * @throws Exception
     */
    public function deleteSchedule($filters): void
    {
        //todo checkFilter kullanarak düzenle
        $scheduleData = array_diff_key($filters, array_flip(["day", "day_index", "classroom_name"]));// day ve day_index alanları çıkartılıyor
        if ($scheduleData['owner_type'] == "classroom") {
            $classroom = (new Classroom())->find($scheduleData['owner_id']) ?: throw new Exception("Derslik Bulunamadı");
            if ($classroom->type == 3) return; // uzaktan eğitim sınıfı ise programa kaydı yoktur
        }
        $schedules = (new Schedule())->get()->where($scheduleData)->all();

        if (!$schedules) {
            throw new Exception("Silinecek ders programı bulunamadı");
        }
        foreach ($schedules as $schedule) {
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
                $index = array_search($filters['day'], $schedule->{"day" . $filters["day_index"]});// dizide dersin indexsi bulunuyor.
                if ($index !== false) {
                    array_splice($schedule->{"day" . $filters["day_index"]}, $index, 1);
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

    /**
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
                "user" => $lesson->getLecturer()?->id ?? null,
                "program" => $lesson->getProgram()?->id ?? null,
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

    /**
     * todo
     * kullanılacağı alana göre filtrede olması gereken alanları kontrol edip fazlalıkları silip eksiklerde hata verir
     * todo semester ve semester no bilgisinin gelip gelmediği birleştirilip birleştirilmeyeceği burada ayarlanmalı.
     * @param array $data
     * @param  $for string Filtrenin neresi için kullanılacağını belirtir
     * @param bool $acceptNull
     * @return array
     * @throws Exception
     */
    public function checkFilters(array $data, string $for, bool $acceptNull = false): array
    {
        /**
         * Ders programı için filtreleme seçeneklerini içeren dizi.
         *
         * @var array $allFilters [
         *     type: string|string[], // Ders programı türü (örneğin: "exam", "lesson")
         *     owner_type: string|string[], // Ders programının ait olduğu birim türü (örneğin: "user", "lesson", "classroom", "program")
         *     owner_id: int|int[], // Ders programının ait olduğu birimin ID numarası
         *     semester: string|string[], // Ders programının ait olduğu dönem (örneğin: "Güz", "Bahar")
         *     academic_year: string|string[], // Akademik yıl bilgisi (örneğin: "2024 - 2025")
         *     semester_no: int|int[], // Dersin ait olduğu yarıyıl numarası
         *     time: string|string[], // Dersin zaman bilgisi (örneğin: "10.00 - 10.50")
         *     day_index: int, // Haftanın günü (0 = Pazar, 1 = Pazartesi, ...)
         *     day: array{lesson_id: int, lecturer_id: int, classroom_id: int}, // Gün bilgisi içeren dizi
         *     time: string, // Dersin başlangıç saati (örn: "10:00")
         *     lesson_hours: int, // Dersin kaç saat süreceği
         *     owners: string[] // Ders programının ait olduğu birim türleri listesi
         *     lesson_id: int, // İşlem yapılacak ders id numarsı
         *     classroom_id: int, // Dersin yapılacağı dersliğin id numarası
         * ]
         */
        $allFilters = [
            "type", // string | array -> Ders programı türünü belirtir (exam, lesson)
            "owner_type", // string | array -> Ders programının ait olduğu birimi belirtir (user, lesson, classroom, program)
            "owner_id", // int | array -> Ders programının ait olduğu birimin ID numarası
            "semester", // string | array -> Ders programının ait olduğu dönem (Güz, Bahar)
            "academic_year", // string | array -> Ders programının ait olduğu akademik yıl (2024 - 2025)
            "semester_no", // int | array -> Dersin ait olduğu yarıyıl numarası
            "time", // string | array -> Dersin saat aralığı (10.00 - 10.50)
            "day_index", // int -> Dersin gün index numarası (0 = Pazar, 1 = Pazartesi, ...)
            "day", // array -> Gün bilgisi içeren dizi (lesson_id, lecturer_id, classroom_id)
            "lesson_hours", // int -> Dersin kaç saatlik olduğu
            "owners", // array[string] -> Ders programının ait olduğu birim türleri listesi
            "lesson_id", // İşlem yapılacak ders id numarsı
            "classroom_id", // Dersin yapılacağı dersliğin id numarası
            "lecturer_id", // Ders programının hoca id numarası
        ];
        $mustFilters = [
            "saveSchedule" => ["type", "semester", "academic_year", "semester_no", "time", "day_index", "lesson_hours", "lesson_id", "classroom_id"],
            "deleteScheduleAction" => ["type", "semester", "academic_year", "semester_no", "time", "day_index", "lesson_id", "classroom_id", "lecturer_id"],
            "deleteSchedule" => ["type", "semester", "academic_year", "semester_no", "time", "day_index", "lesson_id", "classroom_id", "lecturer_id", "owner_type", "owner_id", "day"],
            "checkScheduleCrash" => ["type", "semester", "academic_year", "time", "day_index", "lesson_hours", "lesson_id", "classroom_id"],
        ];

        // ilk olarak gelen filtrelerin içinde allFilters dizisinde belirtilenler dışında bir veri var mı diye kontol et
        foreach ($data as $k => $v) {
            if (!in_array($k, $allFilters)) throw new Exception("$for işleminde geçersiz filtre tespit edildi $k => $v");
        }
        // akademikyıl ve dönem bilgisini kontrol et yok ise ayarlardan ekle
        if (!key_exists("semester", $data)) {
            $data['semester'] = getSettingValue('semester');
        }
        if (!key_exists("academic_year", $data)) {
            $data['academic_year'] = getSettingValue("academic_year");
        }
        // yapılacak işlem için zorunlu filtreleri kontrol et ve eksik durumunda hata ver.
        foreach ($mustFilters[$for] as $filter) {
            if (!key_exists($filter, $data)) throw new Exception("$for işleminde eksik filtre tespit edildi:" . var_export($filter, true));
        }
        $filters = [];
        foreach ($mustFilters[$for] as $filter) {
            $filters[$filter] = $data[$filter] ?? null;
        }
        if (!$acceptNull)
            $filters = array_filter($filters, function ($value) {
                return $value !== null && $value !== '' && $value !== "null";
            });

        return $filters;
    }
}