<?php

namespace App\Models;

use App\Core\Model;

class Schedule extends Model
{
    protected $table = "schedule";

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
     * @var string|null $season 1. Yarıyıl, 2. Yarıyıl ...
     */
    public ?string $season = null;
    /**
     * Pazartesi günü için time alanında belirlenen saatteki program bilgileri
     *  array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|bool|null
     */
    public array|bool|null $day0 = null;
    /**
     * Salı günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day1 = null;
    /**
     * Çarşamba günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day2 = null;
    /**
     * Perşembe günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day3 = null;
    /**
     * Cuma günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day4 = null;
    /**
     * Cumartesi günü için time alanında belirlenen saatteki program bilgileri
     *   array("lecturer_id"=1,"classroom_id"=>1,"lesson_id"=>1)
     * @var array|null
     */
    public array|bool|null $day5 = null;

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Tablo oluştururken günler döngüye sokulurken kullanılır
     * @return array
     */
    public function getWeek()
    {
        return [
            $this->day0,
            $this->day1,
            $this->day2,
            $this->day3,
            $this->day4,
            $this->day5,
        ];
    }
}