<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Schedule;
use PDO;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedule';
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
                } else throw new \Exception("Schedule not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    /**
     * filter ile belirtrilen alanlara uyan Schedule modellerini döner
     * @param array $filters Where koşulunda kullanılmak üzere belirlenmiş alanlardan oluşan bir dizi
     * @return array Schedule Modellerinden oluşan bir array
     */
    public function getSchedules(array $filters = [])
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
            $sql = "SELECT * FROM $this->table_name $whereClause";
            $stmt = $this->database->prepare($sql);

            // Parametreleri bağla
            foreach ($parameters as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            // Verileri işle
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $schedules = [];

            if ($result) {
                foreach ($result as $schedule_data) {
                    $schedule = new Schedule();
                    $schedule->fill($schedule_data);
                    $schedules[] = $schedule;
                }
            }

            return $schedules;

        } catch (\Exception $e) {
            echo $e->getMessage();
            return [];
        }
    }


    /**
     * Filter ile belirlenmiş alanlara uyan Schedule modelleri ile doldurulmış bir HTML tablo döner
     * @param array $filters Where koşulunda kullanılmak üzere belirlenmiş alanlardan oluşan bir dizi
     * @return string
     * todo bu metod Modellerde kullanılarak AdminRouter da ve program gösterilen sayfalarda ScheduleController kullanımı kaldırılabilir.
     */
    public function createScheduleTable(array $filters = []): string
    {
        $schedules = $this->getSchedules($filters);
        $season = isset($filters['season']) ? 'data-season="' . $filters['season'] . '"' : "";
        $tableRows = [
            "08.00-08.50" => (object)$this->emptyWeek,
            "09.00-08.50" => (object)$this->emptyWeek,
            "10.00-10.50" => (object)$this->emptyWeek,
            "11.00-11.50" => (object)$this->emptyWeek,
            "12.00-12.50" => (object)$this->emptyWeek,
            "13.00-13.50" => (object)$this->emptyWeek,
            "14.00-14.50" => (object)$this->emptyWeek,
            "15.00-15.50" => (object)$this->emptyWeek,
            "16.00-16.50" => (object)$this->emptyWeek
        ];
        foreach ($schedules as $schedule) {
            $tableRows[$schedule->time] = $schedule->getWeek();
        }
        $out =
            '
            <table class="table table-bordered table-sm" ' . $season . '>
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
            foreach ($tableRow as $tableColumn) {
                /*
                 * Eğer bir ders kaydedilmişse tableColumn true yada false değildir. Dizi olarak ders sınıf ve hoca bilgisini tutar
                 */
                if (gettype($tableColumn) !== "boolean" && !is_null($tableColumn)) {
                    $tableColumn = (object)$tableColumn;
                    $lesson =(new LessonController())->getLesson($tableColumn->lesson_id);
                    $out .= '
                            <td>
                                <div 
                                id="scheduleTable-lesson-' . $tableColumn->lesson_id . '"
                                draggable="true" 
                                class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                                data-id="scheduleTable-lesson-' . $tableColumn->lesson_id . '"
                                data-lesson-code="'.$lesson->code.'">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold" id="lecturer-' . $tableColumn->lecturer_id . '"><i class="bi bi-book"></i>' . $lesson->getFullName() . '</div>
                                        <div id="classroom-' . $tableColumn->classroom_id . '">' . (new UserController())->getUser($tableColumn->lecturer_id)->getFullName() . '</div>
                                    </div>
                                    <span class="badge bg-info rounded-pill"><i class="bi bi-door-open"></i>' . (new ClassroomController())->getClassroom($tableColumn->classroom_id)->name . '</span>
                                </div>
                            </td>';
                } else {
                    /*
                     * tableColumn bir boolean verisi tutar bu da program eklemeye uygun olup olmadığını gösterir
                     */
                    /*
                     * eğer true ise dropzone sınıflı bir sütun eklenir
                     */
                    if ($tableColumn) {
                        $out .= '
                        <td class="drop-zone">
                        
                        </td>';
                    } else {
                        /*
                         * Eğer false ise drop-zone sınıfı eklenmez ve kırmızı ile vurgulanır
                         */
                        $out .= '
                        <td class="bg-danger">
                        
                        </td>';
                    }
                }

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
    public function availableLessons(array $filters = [])
    {
        $program_table_name = "";
        $available_lessons = [];
        if (array_key_exists('owner_type', $filters) and array_key_exists('owner_id', $filters)) {
            $program_table_name = $filters['owner_type'] . "s";
            if ($filters['owner_type'] == "program") {
                $lessonFilters = [];
                if (array_key_exists("season", $filters)) {
                    $lessonFilters['season'] = $filters['season'];
                }
                $lessonFilters['program_id'] = $filters['owner_id'];
                $lessonsList = (new LessonController())->getLessonsListByFilters($lessonFilters);
                foreach ($lessonsList as $lesson) {
                    $this->checkIsScheduleComplete(['owner_type' => 'lesson', 'owner_id' => $lesson->id]);
                    $lesson->lecturer_name = $lesson->getLecturer()->getFullName();
                    $lesson->hours -= $this->getCount(['owner_type' => 'lesson', 'owner_id' => $lesson->id]);
                    $available_lessons[] = $lesson;
                }
            }
        }
        return $available_lessons;
    }

    public function checkIsScheduleComplete(array $filters = [])
    {
        if (array_key_exists('owner_type', $filters) and array_key_exists('owner_id', $filters)) {
            //ders saati ile schedule programındaki satır saysı eşleşmiyorsa ders tamamlanmamış demektir
            if ($filters['owner_type'] == "lesson") {
                $schedules = $this->getSchedules($filters);
                $lessonController = new LessonController();
                $lesson = $lessonController->getLesson($filters['owner_id']);
                if (count($schedules) < $lesson->hours) {
                    return false;
                } else return true;
            }
        }
    }

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

        } catch (\Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    public function saveSchedule(Schedule $new_schedule): array
    {//todo düzenlenmesi gerekebilir.
        try {
            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_schedule_arr = $new_schedule->getArray(['table_name', 'database', 'id']);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_schedule_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_schedule_arr);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Benzersizlik hatası." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }
}