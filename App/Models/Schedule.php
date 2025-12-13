<?php

namespace App\Models;

use App\Core\Model;
use function App\Helpers\getSettingValue;

class Schedule extends Model
{
    protected string $table_name = "schedules";
    protected array $excludeFromDb = ['items'];  

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

    /**
     * @var ScheduleItem[]
     */
    public array $items = [];

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function getItemsRelation(array $results, array $options = []): array
    {
        $scheduleIds = array_column($results, 'id');
        if (empty($scheduleIds))
            return $results;

        $query = (new ScheduleItem())
            ->get()
            ->where(['schedule_id' => ['in' => $scheduleIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $itemsRaw = $query->all();

        $itemsByScheduleId = [];
        foreach ($itemsRaw as $item) {
            $itemsByScheduleId[$item->schedule_id][] = $item;
        }

        foreach ($results as &$scheduleRow) {
            $scheduleId = $scheduleRow['id'];
            $scheduleRow['items'] = $itemsByScheduleId[$scheduleId] ?? [];
        }

        return $results;
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

    /**
     * Belirtilen özelliklere sahip kaydı getirir, yoksa oluşturur.
     * @param array $attributes
     * @return Schedule
     * @throws \Exception
     */
    public function firstOrCreate(array $attributes): Schedule
    {
        // Mevcut kaydı ara
        $instance = (new self())->get()->where($attributes)->with("items")->first();

        if ($instance) {
            return $instance;
        }

        // Yoksa yeni oluştur
        $instance = new self();
        $instance->fill($attributes);
        $instance->create();

        return $instance;
    }
}