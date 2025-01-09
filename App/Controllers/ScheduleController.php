<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Schedule;
use PDO;

class ScheduleController extends Controller
{

    protected string $table_name = 'schedule';
    private $emptyWeek = array(
        "day0" => true,//Pazartesi
        "day1" => true,//Salı
        "day2" => true,//Çarşamba
        "day3" => true,//Perşembe
        "day4" => true,//Cuma
        "day5" => true //Cumartesi
    );

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
     * @param string|null $owner_type
     * @param int|null $owner_id
     * @return array|void Schedule Modellerinden oluşan bir array
     */
    public function getSchedules(?string $owner_type = null, ?int $owner_id = null)
    {
        if (!is_null($owner_type) && !is_null($owner_id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where owner_type=:owner_type and owner_id=:owner_id");
                $stmt->bindValue(":owner_type", $owner_type, PDO::PARAM_STR);
                $stmt->bindValue(":owner_id", $owner_id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if ($stmt) {
                    $schedules = [];
                    foreach ($stmt as $schedule_data) {
                        $schedule = new Schedule();
                        $schedule->fill($schedule_data);
                        $schedules[] = $schedule;
                    }
                }
                return $schedules;
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * @param string|null $owner_type
     * @param int|null $owner_id
     * @return string
     */
    public function createScheduleTable(?string $owner_type = null, ?int $owner_id = null, ?string $season = null): string
    {
        $schedules = $this->getSchedules($owner_type, $owner_id);

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
            <table class="table table-bordered table-sm">
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
                $out .= '<td>';
                if (gettype($tableColumn) !== "boolean" && !is_null($tableColumn)) {
                    $tableColumn = (object)$tableColumn;
                    $out .= '<div class="schedule-item bg-success p-1">
                                    <div class="schedule-lesson"><i class="fas fa-book-open"></i> ' .
                        (new LessonController())->getLesson($tableColumn->lesson_id)->name . '
                                    </div>
                                    <div class="schedule-lecturer"><i class="fas fa-user"></i> ' .
                        (new UserController())->getUser($tableColumn->lecturer_id)->getFullName()
                        . '</div>
                                    <div class="schedule-classroom"><i class="fas fa-chalkboard"></i> ' .
                        (new ClassroomController())->getClassroom($tableColumn->classroom_id)->name
                        . '</div>
                                </div>';
                }
                $out .= '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody>
               </table>';
        return $out;
    }
}