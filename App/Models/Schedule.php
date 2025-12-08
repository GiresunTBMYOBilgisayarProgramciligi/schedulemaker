<?php

namespace App\Models;

use App\Core\Model;
use function App\Helpers\getSettingValue;

class Schedule extends Model
{
    protected string $table_name = "schedules";

    public ?int $id = null;
    /**
     * @var string|null Program türü lesson, midterm-exam, final-exam, makeup-exam
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
     * Sınıf ayrımı yapmak için kullanılır
     * @var int|null $semester_no 1,2 ...
     */
    public ?int $semester_no = null;
    public ?string $semester = null;
    public ?string $academic_year = null;

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