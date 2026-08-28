<?php

namespace App\Services\Export\Ics;

use App\Core\Gate;
use App\Enums\ExamType;
use App\Enums\PermissionType;
use App\Enums\ScheduleItemStatus;
use App\Models\Schedule;

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
    /**
     * @param array $filters
     * @param array $showOptions
     * @return array
     */
    protected function buildIcs(array $filters, array $showOptions): array
    {
        $type     = $filters['type'];
        $timezone = new \DateTimeZone('Europe/Istanbul');
        $now      = new \DateTime('now', $timezone);

        // Sınav programı için tarihleri sınavın kendi ayarlarından al (BaseIcsExporter üzerinden)
        ['startDate' => $startDate, 'endDate' => $endDate] = $this->getScheduleDates($timezone, $type);

        $typeLabels = [
            ExamType::MIDTERM->value => 'Ara Sınav',
            ExamType::FINAL->value   => 'Final',
            ExamType::MAKEUP->value  => 'Bütünleme',
        ];
        $typeLabel = $typeLabels[$type] ?? 'Sınav';

        $scheduleFilters = $this->filterBuilder->build($filters);
        $lastFilterKey   = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastFilterKey !== null && isset($scheduleFilters[$lastFilterKey]['file_title']))
            ? $scheduleFilters[$lastFilterKey]['file_title']
            : 'Sınav Programı';
        $username        = $this->logContext()['username'] ?? "Sistem";
        $this->logger()->info(
            "{$username} {$fileTitle} takvim çıktısı aldı.",
            $this->logContext()
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//schedulemaker//TR MBMYO Sinav Programi//TR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcsText($filters['academic_year'] . ' ' . $filters['semester'] . ' ' . $typeLabel . ' Programı'),
            'X-WR-TIMEZONE:Europe/Istanbul',
        ];

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) continue;

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) continue;

            foreach ($schedule->items as $scheduleItem) {
                // Tercih/müsait değil item'lerini dışa aktarma
                if (in_array($scheduleItem->status, [ScheduleItemStatus::PREFERRED->value, ScheduleItemStatus::UNAVAILABLE->value])) {
                    continue;
                }
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

                    $firstDate = $this->resolveSingleDate($dayIndex, $weekIndex, $timezone, $now, $startDate);

                    $dtStart = new \DateTime($firstDate->format('Y-m-d') . ' ' . $startText, $timezone);
                    $dtEnd   = new \DateTime($firstDate->format('Y-m-d') . ' ' . $endText, $timezone);

                    $summaryText = "[{$typeLabel}] " . $lesson->name;
                    if (!empty($lesson->code)) $summaryText .= " ({$lesson->code})";

                    $descriptionParts = [];

                        // Derslik ve gözetmenler assignments'tan gelir
                    if ($assignments !== null) {
                        $locationText = implode(', ', array_unique(array_filter(
                            array_column($assignments, 'classroom_name')
                        )));

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
                        } elseif ($scheduleFilter['type'] === 'classroom' && ($showOptions['show_observer'] ?? false) && $lecturer) {
                            $descriptionParts[] = "Gözetmen: " . $lecturer->getFullName();
                        }
                    }

                    // Hoca Adı (Daima dersin asıl hocası)
                    if ($data->lesson?->lecturer) {
                        $descriptionParts[] = "Hoca: " . $data->lesson->lecturer->getFullName();
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
        return $lines;
    }

    /**
     * Sınavlar tek seferlik etkinlik — haftalık tekrar olmaz.
     * week_index ve day_index'e göre dönemin başından itibaren doğru tarihi hesaplar.
     */
    private function resolveSingleDate(int $dayIndex, int $weekIndex, \DateTimeZone $tz, \DateTime $now, ?\DateTime $startDate): \DateTime
    {
        if ($startDate instanceof \DateTime) {
            $targetDow = $dayIndex + 1; // 1=Pzt ... 7=Pz
            $startDow  = (int) $startDate->format('N');
            $delta     = ($targetDow - $startDow + 7) % 7;
            return (clone $startDate)->modify("+{$delta} days")->modify("+{$weekIndex} weeks");
        }

        // Dönem başlangıcı ayarlanmamışsa: bu haftadan başla
        $anchor = new \DateTime('next monday', $tz);
        if ((int) $now->format('N') === 1) $anchor = new \DateTime('today', $tz);
        return (clone $anchor)->modify("+{$dayIndex} day")->modify("+{$weekIndex} weeks");
    }
}
