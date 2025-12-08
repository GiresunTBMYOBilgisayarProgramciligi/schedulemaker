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
}
