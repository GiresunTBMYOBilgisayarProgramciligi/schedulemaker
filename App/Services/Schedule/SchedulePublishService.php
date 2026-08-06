<?php

namespace App\Services\Schedule;

use App\Models\Schedule;
use App\Models\ScheduleChangeQueue;
use App\Repositories\ScheduleRepository;
use App\Core\EventDispatcher;
use App\Events\ScheduleChangesNotifiedEvent;
use Exception;
use function App\Helpers\getSettingValue;

class SchedulePublishService
{
    /**
     * @throws Exception
     */
    public function togglePublish(int $scheduleId): array
    {
        /** @var Schedule|null $schedule */
        $schedule = (new ScheduleRepository())->find($scheduleId);
        if (!$schedule) {
            throw new Exception("Program bulunamadı");
        }

        $schedule->is_published = !$schedule->is_published;
        $schedule->published_at = $schedule->is_published ? date('Y-m-d H:i:s') : null;
        $schedule->update();

        return [
            "status" => "success",
            "msg" => "Program " . ($schedule->is_published ? "yayınlandı" : "yayından kaldırıldı"),
            "is_published" => $schedule->is_published
        ];
    }

    /**
     * @throws Exception
     */
    public function bulkPublish(?string $semester = null, ?string $academicYear = null): int
    {
        $semester = $semester ?? getSettingValue('semester');
        $academicYear = $academicYear ?? getSettingValue('academic_year');

        $schedules = (new Schedule())->get()->where([
            'semester' => $semester,
            'academic_year' => $academicYear
        ])->all();

        $count = 0;
        foreach ($schedules as $schedule) {
            if (!$schedule->is_published) {
                $schedule->is_published = true;
                $schedule->published_at = date('Y-m-d H:i:s');
                $schedule->update();
                $count++;
            }
        }

        return $count;
    }

    /**
     * @throws Exception
     */
    public function notifyChanges(): int
    {
        $changes = (new ScheduleChangeQueue())->get()->all();
        if (empty($changes)) {
            return 0;
        }

        $groupedByLecturer = [];
        foreach ($changes as $change) {
            if ($change->lecturer_id) {
                $groupedByLecturer[$change->lecturer_id][] = $change;
            }
        }

        $notifiedCount = 0;
        foreach ($groupedByLecturer as $lecturerId => $lecturerChanges) {
            EventDispatcher::getInstance()->dispatch(
                new ScheduleChangesNotifiedEvent((int)$lecturerId, $lecturerChanges)
            );
            
            // Delete queued items for this lecturer
            $ids = array_map(fn($c) => $c->id, $lecturerChanges);
            if (!empty($ids)) {
                $queueModel = new ScheduleChangeQueue();
                $queueModel->get()->where(['id' => ['in' => $ids]])->delete();
            }
            $notifiedCount++;
        }

        return $notifiedCount;
    }

    public function recordChange(int $scheduleId, string $actionType, string $detail, ?int $lecturerId = null): void
    {
        $schedule = (new Schedule())->find($scheduleId);
        if ($schedule) {
            $schedule->updated_at = date('Y-m-d H:i:s');
            $schedule->update();

            if ($schedule->is_published) {
                $queue = new ScheduleChangeQueue();
                $queue->fill([
                    'schedule_id' => $scheduleId,
                    'lecturer_id' => $lecturerId,
                    'action_type' => $actionType,
                    'detail' => $detail
                ]);
                $queue->create();
            }
        }
    }
}
