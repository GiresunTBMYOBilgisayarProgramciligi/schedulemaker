<?php

namespace App\Models;

use App\Core\Model;
use function App\Helpers\getSettingValue;

class Schedule extends Model
{
    protected string $table_name = "schedule";

    public ?int $id = null;
    /**
     * Final sınavları iki haftalık olduğu için exam ve exam-2 türü ile kontrol edilecek
     * @var string|null Program türü lesson, exam,exam-2 observer (Ders, sınav, gözetmen)
     */
    public ?string $type = null;
    /**
     * @var string|null Programın sahibinin türü user,lesson,classroom, program
     */
    public ?string $owner_type = null;
    /**
     * @var int|null Program sahibinin id numarası
     */
    public ?int $owner_id = null;
    /**
     * "08.00-08.50", "09.00-09.50", "10.00-10.50",
     * "11.00-11.50", "12.00-12.50", "13.00-13.50",
     * "14.00-14.50", "15.00-15.50", "16.00-16.50",
     * sınav saatleri de yarımsaatlik aralıklarla oluşturulabilir.
     * @var string|null Program saati
     */
    public ?string $time = null;
    /**
     * Sınıf ayrımı yapmak için kullanılır
     * @var int|null $semester_no 1,2 ...
     */
    public ?int $semester_no = null;
    /**
     * Pazartesi günü için time alanında belirlenen saatteki program bilgileri
     *  array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|bool|null
     */
    public array|bool|null $day0 = null;
    /**
     * Salı günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day1 = null;
    /**
     * Çarşamba günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day2 = null;
    /**
     * Perşembe günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day3 = null;
    /**
     * Cuma günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day4 = null;
    /**
     * Cumartesi günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day5 = null;
    /**
     * PAzar günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=>1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day6 = null;
    public ?string $semester = null;
    public ?string $academic_year = null;
    protected array $excludeFromDb = [];


    /**
     * Tablo oluştururken günler döngüye sokulurken kullanılır
     * @param string $type html | excel
     * @param int|null $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     * @throws \Exception
     */
    public function getWeek(string $type = 'html', ?int $maxDayIndex = null): array
    {
        $maxDayIndex = $maxDayIndex ?? getSettingValue('maxDayIndex', 'lesson', 4);
        $week = [];
        foreach (range(0, $maxDayIndex) as $dayIndex) {
            if ($type === 'excel') {
                $day = $this->{"day{$dayIndex}"};
                if (is_array($day)) {
                    //günde ders var
                    if (isset($day[0]) and is_array($day[0])) {
                        // günde iki ders var
                        $groupDayLessons = [];
                        $groupDayClasrooms = [];
                        foreach ($day as $groupLesson) {
                            $groupDayLessons[] = ['lesson_id' => $groupLesson['lesson_id'], 'lecturer_id' => $groupLesson['lecturer_id']]; // ders bilgileri
                            $groupDayClasrooms[] = ['classroom_id' => $groupLesson['classroom_id']];// sınıf bilgileri
                        }
                        $week["day{$dayIndex}"] = $groupDayLessons;
                        $week["classroom{$dayIndex}"] = $groupDayClasrooms;
                    } else {
                        // günde tek ders var
                        $week["day{$dayIndex}"] = ['lesson_id' => $day['lesson_id'], 'lecturer_id' => $day['lecturer_id']]; // ders bilgileri
                        $week["classroom{$dayIndex}"] = ['classroom_id' => $day['classroom_id']];// sınıf bilgileri
                    }
                } else {
                    //günde ders yok
                    $week["day{$dayIndex}"] = null;// ders bilgileri
                    $week["classroom{$dayIndex}"] = null;// sınıf bilgileri
                }

            } else
                $week["day{$dayIndex}"] = $this->{"day{$dayIndex}"};
        }
        return $week;
    }

    public function getdayName($dayString): string
    {
        $days = [
            "day0" => "Pazartesi",
            "day1" => "Salı",
            "day2" => "Çarşamba",
            "day3" => "Perşembe",
            "day4" => "Cuma",
            "day5" => "Cumartesi",
        ];
        return $days[$dayString];
    }

    public function getOwnerTypeScreenName(): string
    {
        $names = [
            "user" => "Hoca",
            "lesson" => "Ders",
            "program" => "Program",
            "classroom" => "Derslik",
        ];
        return $names[$this->owner_type];
    }
}