<?php

namespace App\Models;

use App\Core\Model;

class ScheduleItem extends Model
{
    protected string $table_name = "schedule_items";

    public ?int $id = null;
    public ?int $schedule_id = null;
    public ?int $day_index = null;
    public ?int $week_index = 0;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?string $status = null;
    public ?array $data = null;
    public ?string $description = null;

    public ?Schedule $schedule = null;

    /**
     * @param array $results
     * @return array
     * @throws \Exception
     */
    public function getScheduleRelation(array $results): array
    {
        $scheduleIds = array_column($results, 'schedule_id');
        $scheduleIds = array_unique($scheduleIds);

        if (empty($scheduleIds))
            return $results;

        $schedules = (new Schedule())
            ->get()
            ->where(['id' => ['in' => $scheduleIds]])
            ->all();

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
}
