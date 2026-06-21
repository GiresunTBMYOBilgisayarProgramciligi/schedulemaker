<?php

namespace App\Services\Export\Ics;

use App\Models\Schedule;
use JetBrains\PhpStorm\NoReturn;

/**
 * Ders programını ICS takvim formatında dışa aktarır.
 */
class LessonScheduleIcsExporter extends BaseIcsExporter
{
    #[NoReturn]
    public function export(array $filters, array $showOptions): void
    {
        $timezone = new \DateTimeZone('Europe/Istanbul');
        $now      = new \DateTime('now', $timezone);

        ['semesterStart' => $semesterStart, 'semesterEnd' => $semesterEnd] = $this->getSemesterDates($timezone);

        $lines   = $this->buildCalendarHeader($filters);

        foreach ($this->filterBuilder->build($filters) as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) continue;

            foreach ($schedule->items as $scheduleItem) {
                $startText = $scheduleItem->getShortStartTime();
                $endText   = $scheduleItem->getShortEndTime();
                if (empty($startText) || empty($endText)) continue;

                $dayIndex  = $scheduleItem->day_index;
                $slotDatas = $scheduleItem->getSlotDatas();

                foreach ($slotDatas as $data) {
                    $lesson = $data->lesson;
                    if (!$lesson) continue;

                    $lecturer  = $data->lecturer;
                    $classroom = $data->classroom;

                    [$firstDate, $useRecurrence] = $this->resolveFirstDate($dayIndex, $timezone, $now, $semesterStart);

                    $dtStart = new \DateTime($firstDate->format('Y-m-d') . ' ' . $startText, $timezone);
                    $dtEnd   = new \DateTime($firstDate->format('Y-m-d') . ' ' . $endText, $timezone);

                    $summaryText = $lesson->name;
                    if (!empty($lesson->code)) $summaryText .= " ({$lesson->code})";

                    $locationText   = $classroom ? $classroom->name : '';
                    $descriptionParts = [];

                    if ($scheduleFilter['type'] !== 'user' && $lecturer) {
                        $descriptionParts[] = "Hoca: " . $lecturer->getFullName();
                    }
                    if ($scheduleFilter['type'] !== 'program' && $scheduleFilter['type'] !== 'department' && $lesson->program) {
                        $descriptionParts[] = "Program: " . $lesson->program->name;
                    }
                    $descriptionParts[] = 'Akademik Yıl: ' . $filters['academic_year'];
                    $descriptionParts[] = 'Dönem: ' . $filters['semester'];

                    $lines = array_merge($lines, $this->buildVevent(
                        $dtStart, $dtEnd, $summaryText, $locationText,
                        implode('\n', $descriptionParts),
                        $now, $useRecurrence, $dayIndex, $semesterEnd, $timezone
                    ));
                }
            }
        }

        $lines[]  = 'END:VCALENDAR';
        $fileName = $this->slugify($filters['academic_year'] . '-' . $filters['semester']) . '-ders-programi.ics';
        $this->sendIcsResponse($lines, $fileName);
    }

    private function buildCalendarHeader(array $filters): array
    {
        return [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//schedulemaker//TR MBMYO Ders Programı//TR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcsText($filters['academic_year'] . ' ' . $filters['semester'] . ' Ders Programı'),
            'X-WR-TIMEZONE:Europe/Istanbul',
        ];
    }

    private function resolveFirstDate(int $dayIndex, \DateTimeZone $tz, \DateTime $now, ?\DateTime $semesterStart): array
    {
        $useRecurrence = $semesterStart instanceof \DateTime;

        if ($useRecurrence) {
            $targetDow = $dayIndex + 1;
            $startDow  = (int) $semesterStart->format('N');
            $delta     = ($targetDow - $startDow + 7) % 7;
            $firstDate = (clone $semesterStart)->modify("+{$delta} days");
        } else {
            $anchor    = new \DateTime('next monday', $tz);
            if ((int) $now->format('N') === 1) $anchor = new \DateTime('today', $tz);
            $firstDate = (clone $anchor)->modify("+{$dayIndex} day");
        }

        return [$firstDate, $useRecurrence];
    }

    private function buildVevent(
        \DateTime $dtStart,
        \DateTime $dtEnd,
        string $summary,
        string $location,
        string $description,
        \DateTime $now,
        bool $useRecurrence,
        int $dayIndex,
        ?\DateTime $semesterEnd,
        \DateTimeZone $tz
    ): array {
        $uid      = uniqid('sm-', true) . '@schedulemaker.local';
        $dtstamp  = $now->format('Ymd\THis');

        $event = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART;TZID=Europe/Istanbul:' . $dtStart->format('Ymd\THis'),
            'DTEND;TZID=Europe/Istanbul:' . $dtEnd->format('Ymd\THis'),
        ];

        if ($useRecurrence && $semesterEnd instanceof \DateTime) {
            $weekdayCodes = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
            $byday        = $weekdayCodes[$dayIndex] ?? 'MO';
            $untilUtc     = (clone $semesterEnd)->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $event[]      = 'RRULE:FREQ=WEEKLY;UNTIL=' . $untilUtc . ';BYDAY=' . $byday;
        }

        $event[] = 'SUMMARY:' . $this->escapeIcsText($summary);
        if (!empty($location))    $event[] = 'LOCATION:' . $this->escapeIcsText($location);
        if (!empty($description)) $event[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
        $event[] = 'END:VEVENT';

        return $event;
    }
}
