<?php

namespace App\Models;

use App\Core\Model;

class ScheduleItem extends Model
{
    protected string $table_name = "schedule_items";
    protected array $excludeFromDb = ['schedule'];

    public ?int $id = null;
    public ?int $schedule_id = null;
    public ?int $day_index = null;
    public ?int $week_index = 0;
    public ?string $start_time = null;
    public ?string $end_time = null;
    /**
     * ENUM('preferred','unavailable','group','single')
     */
    public ?string $status = null;
    public ?array $data = null;
    public ?array $detail = null;

    public ?Schedule $schedule = null;

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function getScheduleRelation(array $results, array $options = []): array
    {
        $scheduleIds = array_column($results, 'schedule_id');
        $scheduleIds = array_unique($scheduleIds);

        if (empty($scheduleIds))
            return $results;

        $query = (new Schedule())
            ->get()
            ->where(['id' => ['in' => $scheduleIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $schedules = $query->all();

        $schedulesKeyed = [];
        foreach ($schedules as $schedule) {
            $schedulesKeyed[$schedule->id] = $schedule;
        }

        foreach ($results as &$itemRow) {
            if (isset($schedulesKeyed[$itemRow['schedule_id']])) {
                $itemRow['schedule'] = $schedulesKeyed[$itemRow['schedule_id']];
            } else {
                $itemRow['schedule'] = null;
            }
        }

        return $results;
    }

    public function getLessons()
    {
        $lessons = [];
        foreach ($this->data as $dayData) {
            if ($dayData == null)
                continue;
            $lessons[] = (new Lesson()->get()->where(['id' => $dayData['lesson_id']]))->first();
        }
        //$this->logger()->debug('Lessons: ', ['lessons' => $lessons]);
        return $lessons;
    }

    public function getLecturers()
    {
        $lecturers = [];
        foreach ($this->data as $dayData) {
            if ($dayData == null)
                continue;
            $lecturers[] = (new User()->get()->where(['id' => $dayData['lecturer_id']]))->first();
        }
        //$this->logger()->debug('Lecturers: ', ['lecturers' => $lecturers]);
        return $lecturers;
    }

    public function getClassrooms()
    {
        $classrooms = [];
        foreach ($this->data as $dayData) {
            if ($dayData == null)
                continue;
            $classrooms[] = (new Classroom()->get()->where(['id' => $dayData['classroom_id']]))->first();
        }
        //$this->logger()->debug('Classrooms: ', ['classrooms' => $classrooms]);
        return $classrooms;
    }
    public function getSlotDatas()
    {
        $slotDatas = [];
        if ($this->data == null) {
            return $slotDatas;
        }
        foreach ($this->data as $dayData) {
            if ($dayData == null)
                continue;
            $slotDatas[] = (object) [
                'lesson' => (new Lesson())->get()->where(['id' => $dayData['lesson_id']])->with(['childLessons','program'])->first(),
                'lecturer' => (new User())->get()->where(['id' => $dayData['lecturer_id']])->first(),
                'classroom' => (new Classroom())->get()->where(['id' => $dayData['classroom_id']])->first(),
            ];
        }
        //$this->logger()->debug('SlotDatas: ', $this->logContext(['slotDatas' => $slotDatas]));
        return $slotDatas;
    }
    public function getSlotCSSClass()
    {
        switch ($this->status) {
            case 'preferred':
                return 'slot-preferred';
            case 'unavailable':
                return 'slot-unavailable';
            default:
                return '';
        }
    }

    /**
     * @return string H:i formatında başlangıç saati
     */
    public function getShortStartTime(): string
    {
        return $this->start_time ? substr($this->start_time, 0, 5) : "";
    }

    /**
     * @return string H:i formatında bitiş saati
     */
    public function getShortEndTime(): string
    {
        return $this->end_time ? substr($this->end_time, 0, 5) : "";
    }
}
