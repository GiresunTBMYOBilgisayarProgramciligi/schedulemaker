<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedule';
    protected string $modelName = "App\Models\Schedule";
    /**
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır. True değeri o gün program düzelemeye uygun anlamına gelir.
     * @var true[]
     */
    private $emptyWeek = array(
        "day0" => true,//Pazartesi
        "day1" => true,//Salı
        "day2" => true,//Çarşamba
        "day3" => true,//Perşembe
        "day4" => true,//Cuma
        "day5" => true //Cumartesi
    );

    /**
     * Veri tabanından verileri alıp Schedule Modeli ile oluşturulan bir veri döndürür
     * @param int|null $id
     * @return Schedule|void
     */
    public function getSchedule(?int $id = null)
    {
        if (!is_null($id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($stmt) {
                    $schedule = new Schedule();
                    $schedule->fill($stmt);

                    return $schedule;
                } else throw new Exception("Schedule not found");
            } catch (Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    /**
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmış bir HTML tablo döner
     * @param array $filters Where koşulunda kullanılmak üzere belirlenmiş alanlardan oluşan bir dizi
     * @return string
     * todo bu metod Modellerde kullanılarak AdminRouter da ve program gösterilen sayfalarda ScheduleController kullanımı kaldırılabilir.
     * @throws Exception
     */
    public function createScheduleTable(array $filters = []): string
    {
        try {
            $schedules = $this->getListByFilters($filters);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $season = isset($filters['season']) ? 'data-season="' . $filters['season'] . '"' : "";
        /**
         * Boş tablo oluşturmak için tablo satır verileri
         */
        $tableRows = [
            "08.00 - 08.50" => (object)$this->emptyWeek,
            "09.00 - 09.50" => (object)$this->emptyWeek,
            "10.00 - 10.50" => (object)$this->emptyWeek,
            "11.00 - 11.50" => (object)$this->emptyWeek,
            "12.00 - 12.50" => (object)$this->emptyWeek,
            "13.00 - 13.50" => (object)$this->emptyWeek,
            "14.00 - 14.50" => (object)$this->emptyWeek,
            "15.00 - 15.50" => (object)$this->emptyWeek,
            "16.00 - 16.50" => (object)$this->emptyWeek
        ];
        $lessonHourCount = [];
        /*
         * Veri tabanından alınan bilgileri tablo satırları yerine yerleştiriliyor
         */
        foreach ($schedules as $schedule) {
            $tableRows[$schedule->time] = $schedule->getWeek();
        }

        $out =
            '
            <table class="table table-bordered table-sm small" ' . $season . '>
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pazartesi</th>
                                    <th>Salı</th>
                                    <th>Çarşamba</th>
                                    <th>Perşembe</th>
                                    <th>Cuma</th>
                                    <th>Cumartesi</th>
                                </tr>
                                </thead>
                                <tbody>';
        $times = array_keys($tableRows);
        for ($i = 0; $i < count($times); $i++) {
            $tableRow = $tableRows[$times[$i]];
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
                        $out .= '<td>';
                        foreach ($day as $column) {
                            $column = (object)$column; // Array'i objeye dönüştür
                            $lesson = (new LessonController())->getLesson($column->lesson_id);
                            $lessonHourCount[$lesson->id] = is_null($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                            $lecturerName = $lesson->getLecturer()->getFullName();
                            $classroomName = (new ClassroomController())->getClassroom($column->classroom_id)->name;
                            $out .= '
                            <div 
                            id="scheduleTable-lesson-' . $column->lesson_id .'-'. $lessonHourCount[$lesson->id] . '"
                            draggable="true" 
                            class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                            data-lesson-code="' . $lesson->code . '" data-season="' . $lesson->season . '" data-lesson-id="' . $lesson->id . '"
                            data-schedule-time="' . $times[$i] . '"
                            data-schedule-day="' . $dayIndex . '">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold" id="lecturer-' . $column->lecturer_id . '">
                                        <i class="bi bi-book"></i> ' . $lesson->getFullName() . '
                                    </div>
                                    <div id="classroom-' . $column->classroom_id . '">' . $lecturerName . '</div>
                                </div>
                                <span class="badge bg-info rounded-pill">
                                    <i class="bi bi-door-open"></i> ' . $classroomName . '
                                </span>
                            </div>';
                        }
                        $out .= '</td>';
                    } else {
                        // Eğer day bir array ise bilgileri yazdır
                        $day = (object)$day; // Array'i objeye dönüştür
                        $lesson = (new LessonController())->getLesson($day->lesson_id);
                        $lessonHourCount[$lesson->id] = is_null($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                        $lecturerName = $lesson->getLecturer()->getFullName();
                        $classroomName = (new ClassroomController())->getClassroom($day->classroom_id)->name;
                        //Ders gruplu ise drop zone ekle
                        $drop_zone = preg_match('/\.\d+$/', $lesson->code) === 1 ? "drop-zone" : "";
                        $out .= '
                        <td class="' . $drop_zone . '">
                            <div 
                            id="scheduleTable-lesson-' . $day->lesson_id .'-'. $lessonHourCount[$lesson->id] . '"
                            draggable="true" 
                            class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                            data-lesson-code="' . $lesson->code . '" data-season="' . $lesson->season . '" data-lesson-id="' . $lesson->id . '"
                            data-schedule-time="' . $times[$i] . '"
                            data-schedule-day="' . $dayIndex . '">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold" id="lecturer-' . $day->lecturer_id . '">
                                        <i class="bi bi-book"></i> ' . $lesson->getFullName() . '
                                    </div>
                                    <div id="classroom-' . $day->classroom_id . '">' . $lecturerName . '</div>
                                </div>
                                <span class="badge bg-info rounded-pill">
                                    <i class="bi bi-door-open"></i> ' . $classroomName . '
                                </span>
                            </div>
                        </td>';
                    }
                } elseif (is_null($day) || $day === true) {
                    // Eğer null veya true ise boş dropzone ekle
                    $out .= ($day === true && $times[$i] === "12.00 - 12.50")
                        ? '<td class="bg-danger"></td>' // Öğle saatinde kırmızı hücre
                        : '<td class="drop-zone"></td>';
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
     * Ders programı tamamlanmamış olan derslerin bilgilerini döner.
     * @param array $filters
     * @return array
     */
    public function availableLessons(array $filters = []): array
    {
        $available_lessons = [];
        if (array_key_exists('owner_type', $filters) and array_key_exists('owner_id', $filters)) {
            if ($filters['owner_type'] == "program") {
                $lessonFilters = [];
                if (array_key_exists("season", $filters)) {
                    $lessonFilters['season'] = $filters['season'];
                }
                $lessonFilters['program_id'] = $filters['owner_id'];
                $lessonsList = (new LessonController())->getListByFilters($lessonFilters);
                foreach ($lessonsList as $lesson) {
                    if (!$this->checkIsScheduleComplete(['owner_type' => 'lesson', 'owner_id' => $lesson->id])) {
                        $lesson->lecturer_name = $lesson->getLecturer()->getFullName();
                        $lesson->hours -= $this->getCount(['owner_type' => 'lesson', 'owner_id' => $lesson->id]);
                        $available_lessons[] = $lesson;
                    }

                }
            }
        }
        return $available_lessons;
    }

    /**
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

    public function availableClassrooms(array $filters = [])
    {
        try {
            if (!key_exists("hours", $filters) or !key_exists("time", $filters)) {
                throw new \Exception("Missing hours and time");
            }
            $times = $this->generateTimesArrayFromText($filters["time"], $filters["hours"]);
            $available_classrooms = [];
            $unavailable_classroom_ids = [];
            if (array_key_exists('owner_type', $filters)) {
                if ($filters['owner_type'] == "classroom") {
                    $classroomSchedules = $this->getListByFilters(
                        [
                            "time" => $times,
                            "owner_type" => $filters['owner_type'],
                        ]
                    );
                    foreach ($classroomSchedules as $classroomSchedule) {
                        if (!is_null($classroomSchedule->{$filters["day"]})) {
                            $unavailable_classroom_ids[] = $classroomSchedule->owner_id;
                        }
                    }
                    $available_classrooms = (new ClassroomController())->getListByFilters(["!id" => $unavailable_classroom_ids]);
                }
            }
        } catch (Exception $e) {
            return ["status" => "error", "msg" => $e->getMessage()];//todo ajax respose kalıbı her yerde aynı olmalı bunu sağlamak için bir şeyler yapılabilir.
        }

        return ["status" => "success", "classrooms" => $available_classrooms];
    }

    /**
     * @param array $filters
     * @return bool
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        try {
            $result = true;
            if (!key_exists("lesson_hours", $filters) or !key_exists("time_start", $filters)) {
                throw new \Exception("Ders saati yada program saati yok | CheckScheduleCrash");
            }
            $times = $this->generateTimesArrayFromText($filters["time_start"], $filters["lesson_hours"]);

            if (array_key_exists('owners', $filters)) {
                foreach ($filters["owners"] as $owner_type => $owner_id) {
                    $schedules = $this->getListByFilters(
                        [
                            "time" => $times,
                            "owner_type" => $owner_type,
                            "owner_id" => $owner_id,
                            "type" => $filters['type'],
                            "season" => $filters['season']
                        ]
                    );
                    foreach ($schedules as $schedule) {
                        if ($schedule->{$filters["day"]}) {
                            if (is_array($schedule->{$filters["day"]}) and in_array($schedule->owner_type, ["lesson", "program"])) {
                                $lessonCode = (new LessonController())->getLesson($schedule->{$filters["day"]}['lesson_id'])->code;
                                $newLessonCode = (new LessonController())->getLesson($filters['owners']['lesson'])->code;
                                if (preg_match('/\.\d+$/', $lessonCode) !== 1 and preg_match('/\.\d+$/', $newLessonCode) !== 1) {
                                    return false;
                                }
                            } else {
                                return false;
                            }
                        }
                    }
                }

            } else throw new Exception("Owners bilgileri girilmemiş");
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $result;
    }

    /**
     * Gelen owner_type a göre belirlenen koşulların sağlanıp sağlanmadığını kontrol ederek Takvimin tamamlanıp tamamlanmadığını döner
     * @param array $filters kontrol edilecek Modelin bilgilerini içerir lesson, lecturer,program,department
     * @return bool|void
     */
    public function checkIsScheduleComplete(array $filters = [])
    {
        try {
            if (array_key_exists('owner_type', $filters) and array_key_exists('owner_id', $filters)) {
                //ders saati ile schedule programındaki satır saysı eşleşmiyorsa ders tamamlanmamış demektir
                if ($filters['owner_type'] == "lesson") {
                    $schedules = $this->getListByFilters($filters);
                    $lessonController = new LessonController();
                    $lesson = $lessonController->getLesson($filters['owner_id']);
                    if (count($schedules) < $lesson->hours) {
                        return false;
                    } else return true;
                }//todo diğer türler için işlemler
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    /**
     * belirtilen filtreye uyan schedule satırlarının sayısını döner
     * @param array $filters
     * @return false|int|mixed
     */
    public function getCount(array $filters = [])
    {
        try {
            // Koşullar ve parametreler
            $conditions = [];
            $parameters = [];

            // Parametrelerden WHERE koşullarını oluştur
            foreach ($filters as $column => $value) {
                $conditions[] = "$column = :$column";
                $parameters[":$column"] = $value;
            }

            // WHERE ifadesini oluştur
            $whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

            // Sorguyu hazırla
            $sql = "SELECT COUNT(*) as 'count' FROM $this->table_name $whereClause";
            $stmt = $this->database->prepare($sql);

            // Parametreleri bağla
            foreach ($parameters as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            // Verileri işle
            $result = $stmt->fetchColumn();

            return $result;

        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    public function saveNew(Schedule $new_schedule): array
    {
        try {
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
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                try {
                    $updatingSchedule = $this->getListByFilters($new_schedule->getArray(
                        ['table_name', 'database', 'id', 'day0', 'day1', 'day2', 'day3', 'day4', 'day5']))[0];
                    for ($i = 0; $i < 6; $i++) {
                        if (!is_null($new_schedule->{"day" . $i})) {
                            //yeni eklenecek dersin boş günlerini geçiyoruz. sadece dolu olanlar kaydedilecek
                            if (is_array($updatingSchedule->{"day" . $i})) {
                                // yeni bilgi eklenecek alanın verisinin dizi olup olmadığına bakıyoruz. dizi ise bir ders vardır.
                                $lessonCode = (new LessonController())->getLesson($updatingSchedule->{"day" . $i}['lesson_id'])->code;
                                $newLessonCode = (new LessonController())->getLesson($new_schedule->{"day" . $i}['lesson_id'])->code;
                                if (preg_match('/\.\d+$/', $lessonCode) === 1 and preg_match('/\.\d+$/', $newLessonCode) === 1) {
                                    $dayData = [];
                                    $dayData[] = $updatingSchedule->{"day" . $i};
                                    $dayData[] = $new_schedule->{"day" . $i};

                                    $updatingSchedule->{"day" . $i} = $dayData;
                                } else {
                                    throw new Exception("Dersler gruplu değil bu şekilde kaydedilemez");
                                }
                            } else {
                                // ders yada program değil normal güncellemem işi yapılacak
                                $updatingSchedule->{"day" . $i} = $new_schedule->{"day" . $i};
                            }
                        }
                    }
                    $this->updateSchedule($updatingSchedule);
                } catch (Exception $e) {
                    return ["status" => "error", "msg" => "Program Güncellenirken hata oluştu" . $e->getMessage()];
                }
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function updateSchedule(Schedule $schedule)
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
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Unique Çakışması" . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }
        return ["status" => "success"];
    }

    public function deleteSchedule($filters)
    {
        try {
            $scheduleData = array_diff_key($filters, array_flip(["day", "day_index"]));// day ve day_index alanları çıkartılıyor
            $schedule = $this->getListByFilters($scheduleData)[0];
            if (array_key_exists("lesson_id", $schedule->{"day" . $filters["day_index"]})) {
                if ($schedule->{"day" . $filters["day_index"]} == $filters['day']) {
                    $schedule->{"day" . $filters["day_index"]} = null;
                    $weekEmpty = true;
                    for ($i = 0; $i < 6; $i++) { //günler tek tek kontrol edilecek
                        if (!is_null($schedule->{"day" . $i})) {
                            $weekEmpty = false;
                        }
                    }
                    if ($weekEmpty)
                        return $this->delete($schedule->id);
                    else
                        return $this->updateSchedule($schedule);
                }
            } else {
                // Bu durumda günde iki ders var
                for ($i = 0; $i < 2; $i++) {
                    if ($schedule->{"day" . $filters["day_index"]}[$i] == $filters['day']) {
                        unset($schedule->{"day" . $filters["day_index"]}[$i]);
                    }
                }
                $weekEmpty = true;
                for ($i = 0; $i < 6; $i++) { //günler tek tek kontrol edilecek
                    if (!is_null($schedule->{"day" . $i})) {
                        $weekEmpty = false;
                    }
                }
                if ($weekEmpty)
                    return $this->delete($schedule->id);
                else
                    return $this->updateSchedule($schedule);
            }

        } catch (Exception $e) {
            return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
        }
    }
}