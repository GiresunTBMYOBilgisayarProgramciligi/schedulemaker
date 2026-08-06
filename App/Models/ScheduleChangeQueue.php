<?php

namespace App\Models;

use App\Core\Model;

class ScheduleChangeQueue extends Model
{
    protected string $table_name = "schedule_changes_queue";
    protected array $excludeFromDb = ['schedule', 'lecturer'];

    public ?int $id = null;
    public ?int $schedule_id = null;
    public ?int $lecturer_id = null;
    public ?string $action_type = null;
    public ?string $detail = null;
    public ?string $created_at = null;

    public ?Schedule $schedule = null;
    public ?User $lecturer = null;

    public function getScheduleRelation(array $results, array $options = []): array
    {
        $scheduleIds = array_filter(array_unique(array_column($results, 'schedule_id')));
        if (empty($scheduleIds)) return $results;

        $schedules = (new Schedule())->get()->where(['id' => ['in' => $scheduleIds]])->all();
        $schedulesKeyed = [];
        foreach ($schedules as $schedule) {
            $schedulesKeyed[$schedule->id] = $schedule;
        }

        foreach ($results as &$row) {
            $row['schedule'] = $schedulesKeyed[$row['schedule_id']] ?? null;
        }

        return $results;
    }

    public function getLecturerRelation(array $results, array $options = []): array
    {
        $lecturerIds = array_filter(array_unique(array_column($results, 'lecturer_id')));
        if (empty($lecturerIds)) return $results;

        $lecturers = (new User())->get()->where(['id' => ['in' => $lecturerIds]])->all();
        $lecturersKeyed = [];
        foreach ($lecturers as $lecturer) {
            $lecturersKeyed[$lecturer->id] = $lecturer;
        }

        foreach ($results as &$row) {
            $row['lecturer'] = $lecturersKeyed[$row['lecturer_id']] ?? null;
        }

        return $results;
    }
}
