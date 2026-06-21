<?php

namespace App\Services\Export\Ics;

use App\Models\Schedule;
use JetBrains\PhpStorm\NoReturn;

/**
 * Sınav programını (Ara Sınav / Final / Bütünleme) ICS takvim formatında dışa aktarır.
 *
 * Ders programından farkı:
 *  - Olaylar haftalık tekrar içermez (sınavlar tek seferdir)
 *  - Gözetmen bilgisi açıklama (description) alanına eklenir
 *  - Sınav türü başlıkta belirtilir
 */
class ExamScheduleIcsExporter extends BaseIcsExporter
{
    #[NoReturn]
    public function export(array $filters, array $showOptions): void
    {
        $type     = $filters['type'];
        $timezone = new \DateTimeZone('Europe/Istanbul');
        $now      = new \DateTime('now', $timezone);

        // Sınav programı için dönem tarihlerini sınav dönem ayarlarından al
        ['semesterStart' => $semesterStart, 'semesterEnd' => $semesterEnd] = $this->getSemesterDates($timezone);

        $typeLabels = [
            'midterm-exam' => 'Ara Sınav',
            'final-exam'   => 'Final',
            'makeup-exam'  => 'Bütünleme',
        ];
        $typeLabel = $typeLabels[$type] ?? 'Sınav';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//schedulemaker//TR MBMYO Sinav Programi//TR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcsText($filters['academic_year'] . ' ' . $filters['semester'] . ' ' . $typeLabel . ' Programı'),
            'X-WR-TIMEZONE:Europe/Istanbul',
        ];

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

                $dayIndex   = $scheduleItem->day_index;
                $weekIndex  = $scheduleItem->week_index ?? 0;
                $slotDatas  = $scheduleItem->getSlotDatas();

                // Gözetmen bilgisi: Program/Ders bazlı kayıtta detail['assignments'] içinde gelir.
                // Gözetmen/Derslik bazlı kayıtta detail['reference_type'] = 'exam_assignment' olur.
                $assignments   = $scheduleItem->detail['assignments'] ?? null;
                $isAssignmentRecord = ($scheduleItem->detail['reference_type'] ?? null) === 'exam_assignment';

                foreach ($slotDatas as $data) {
                    $lesson = $data->lesson;
                    if (!$lesson) continue;

                    $lecturer  = $data->lecturer;
                    $classroom = $data->classroom;

                    $firstDate = $this->resolveSingleDate($dayIndex, $weekIndex, $timezone, $now, $semesterStart);

                    $dtStart = new \DateTime($firstDate->format('Y-m-d') . ' ' . $startText, $timezone);
                    $dtEnd   = new \DateTime($firstDate->format('Y-m-d') . ' ' . $endText, $timezone);

                    $summaryText = "[{$typeLabel}] " . $lesson->name;
                    if (!empty($lesson->code)) $summaryText .= " ({$lesson->code})";

                    $descriptionParts = [];

                    // A) Program bazlı kayıt: derslik ve gözetmenler assignments'tan gelir
                    if ($assignments !== null) {
                        $locationText = implode(', ', array_unique(array_filter(
                            array_column($assignments, 'classroom_name')
                        )));

                        // Hoca: program bazlı sınav kaydında data.lecturer_id = null,
                        //       dersin kendi lecturer_id'si kullanılır.
                        if (!empty($data->lesson->lecturer_id)) {
                            $lessonLecturer = (new \App\Models\User())
                                ->get()
                                ->where(['id' => $data->lesson->lecturer_id])
                                ->first();
                            if ($lessonLecturer && $scheduleFilter['type'] !== 'user') {
                                $descriptionParts[] = "Hoca: " . $lessonLecturer->getFullName();
                            }
                        }

                        // Gözetmen bilgisi
                        if ($showOptions['show_observer'] ?? false) {
                            $observerNames = array_filter(array_column($assignments, 'observer_name'));
                            if (!empty($observerNames)) {
                                $descriptionParts[] = "Gözetmenler:\n" . implode('\n', $observerNames);
                            }
                        }
                    } else {
                        // B) Gözetmen/Derslik bazlı kayıt: data içinde classroom var
                        $locationText = $classroom ? $classroom->name : '';
                        if ($isAssignmentRecord && $lecturer) {
                            $descriptionParts[] = "Gözetmen: " . $lecturer->getFullName();
                        }
                    }

                    if ($scheduleFilter['type'] !== 'user' && $lecturer && !$isAssignmentRecord) {
                        $descriptionParts[] = "Hoca: " . $lecturer->getFullName();
                    }
                    if ($scheduleFilter['type'] !== 'program' && $scheduleFilter['type'] !== 'department' && $lesson->program) {
                        $descriptionParts[] = "Program: " . $lesson->program->name;
                    }
                    $descriptionParts[] = 'Sınav Türü: ' . $typeLabel;
                    $descriptionParts[] = 'Akademik Yıl: ' . $filters['academic_year'];
                    $descriptionParts[] = 'Dönem: ' . $filters['semester'];

                    $uid     = uniqid('sm-exam-', true) . '@schedulemaker.local';
                    $dtstamp = $now->format('Ymd\THis');

                    $lines[] = 'BEGIN:VEVENT';
                    $lines[] = 'UID:' . $uid;
                    $lines[] = 'DTSTAMP:' . $dtstamp;
                    $lines[] = 'DTSTART;TZID=Europe/Istanbul:' . $dtStart->format('Ymd\THis');
                    $lines[] = 'DTEND;TZID=Europe/Istanbul:' . $dtEnd->format('Ymd\THis');
                    // Sınavlar tekrar etmez — RRULE yok
                    $lines[] = 'SUMMARY:' . $this->escapeIcsText($summaryText);
                    if (!empty($locationText))   $lines[] = 'LOCATION:' . $this->escapeIcsText($locationText);
                    $descriptionText = implode('\n', $descriptionParts);
                    if (!empty($descriptionText)) $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($descriptionText);
                    $lines[] = 'END:VEVENT';
                }
            }
        }

        $lines[]  = 'END:VCALENDAR';
        $fileName = $this->slugify($filters['academic_year'] . '-' . $filters['semester'] . '-' . $type) . '-programi.ics';
        $this->sendIcsResponse($lines, $fileName);
    }

    /**
     * Sınavlar tek seferlik etkinlik — haftalık tekrar olmaz.
     * week_index ve day_index'e göre dönemin başından itibaren doğru tarihi hesaplar.
     */
    private function resolveSingleDate(int $dayIndex, int $weekIndex, \DateTimeZone $tz, \DateTime $now, ?\DateTime $semesterStart): \DateTime
    {
        if ($semesterStart instanceof \DateTime) {
            $targetDow = $dayIndex + 1; // 1=Pzt ... 7=Pz
            $startDow  = (int) $semesterStart->format('N');
            $delta     = ($targetDow - $startDow + 7) % 7;
            return (clone $semesterStart)->modify("+{$delta} days")->modify("+{$weekIndex} weeks");
        }

        // Dönem başlangıcı ayarlanmamışsa: bu haftadan başla
        $anchor = new \DateTime('next monday', $tz);
        if ((int) $now->format('N') === 1) $anchor = new \DateTime('today', $tz);
        return (clone $anchor)->modify("+{$dayIndex} day")->modify("+{$weekIndex} weeks");
    }
}
