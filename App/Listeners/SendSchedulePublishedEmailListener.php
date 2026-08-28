<?php

namespace App\Listeners;

use App\Events\SchedulePublishedEvent;
use App\Models\Schedule;
use App\Models\User;
use App\Enums\OwnerType;
use App\Mailers\ScheduleMailer;
use App\Services\Export\ExporterFactory;
use App\Core\Log;
use Exception;

class SendSchedulePublishedEmailListener
{
    public function handle(SchedulePublishedEvent $event): void
    {
        try {
            /** @var Schedule|null $schedule */
            $schedule = (new Schedule())->get()
                ->where([
                    'id' => $event->scheduleId,
                    'owner_type' => OwnerType::USER->value
                ])
                ->with('items')
                ->first();

            if (!$schedule || !$schedule->owner_id) {
                return;
            }

            /** @var User|null $lecturer */
            $lecturer = (new User())->find($schedule->owner_id);
            if (!$lecturer || empty($lecturer->mail)) {
                return;
            }

            $filters = [
                'type'          => $schedule->type ?? 'lesson',
                'owner_type'    => OwnerType::USER->value,
                'owner_id'      => $lecturer->id,
                'semester'      => $schedule->semester,
                'academic_year' => $schedule->academic_year,
            ];

            $showOptions = [
                'show_code'     => true,
                'show_lecturer' => false,
                'show_program'  => true,
                'show_observer' => false,
            ];

            $excelExporter = ExporterFactory::create($filters, 'excel');
            $excelContent  = $excelExporter->getRawContent($filters, $showOptions);
            $excelFileName = $excelExporter->getFileName($filters);

            $icsExporter   = ExporterFactory::create($filters, 'ics');
            $icsContent    = $icsExporter->getRawContent($filters, $showOptions);
            $icsFileName   = $icsExporter->getFileName($filters);

            $mailer = new ScheduleMailer();
            $queueId = $mailer->queueSchedulePublishedNotification($lecturer, $schedule, $excelContent, $excelFileName, $icsContent, $icsFileName);

            if ($queueId > 0) {
                Log::logger()->info("Ders programı yayınlama bildirimi e-posta kuyruğuna eklendi.", [
                    'queue_id'    => $queueId,
                    'schedule_id' => $schedule->id,
                    'user_id'     => $lecturer->id,
                    'email'       => $lecturer->mail
                ]);
            }
        } catch (\Throwable $e) {
            Log::logger()->error("SendSchedulePublishedEmailListener hatası: " . $e->getMessage(), [
                'schedule_id' => $event->scheduleId,
                'exception'   => $e
            ]);
        }
    }
}
