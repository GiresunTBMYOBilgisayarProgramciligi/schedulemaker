<?php

namespace App\Services\Schedule\ExamRules;

use App\Helpers\TimeHelper;
use App\Models\Program;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Aynı programda farklı sınıf düzeylerinin (1-2, 1-3, 2-4, ...) sınavları zaman olarak çakışmamalıdır.
 *
 * Sınıf, yarıyıl numarasından hesaplanır (getClassFromSemesterNo). MYO'lar için katı bir kural olabilse de
 * fakültelerde zorunlu olmadığı için engelleyici değil, uyarıdır.
 * Aynı birleşik sınava (lesson_combinations, type=exam) ait dersler birbirinin çakışması sayılmaz.
 */
class AdjacentClassOverlapRule implements ExamWarningRule
{
    private const DAYS = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];

    public function check(ExamRuleContext $context): array
    {
        // "program|gün|hafta" => ['program_id', 'day_index', 'schedule', 'entries' => [...]]
        $groups = [];

        foreach ($context->newExams() as $index => $exam) {
            $dto = $exam['dto'];
            $root = $context->examRootId($exam['lesson']);

            foreach ($exam['programs'] as $program) {
                $key = "{$program['program_id']}|{$dto->dayIndex}|{$dto->weekIndex}";
                if (!isset($groups[$key])) {
                    $entries = [];
                    $existing = $context->existingExams($program['program_id'], $exam['schedule'], $dto->dayIndex, $dto->weekIndex);
                    foreach ($existing as $item) {
                        $entries[] = $item + ['is_new' => false];
                    }
                    $groups[$key] = [
                        'program_id' => $program['program_id'],
                        'day_index' => $dto->dayIndex,
                        'entries' => $entries,
                    ];
                }
                $groups[$key]['entries'][] = [
                    'semester_no' => $program['semester_no'],
                    'start' => $dto->startTime,
                    'end' => $dto->endTime,
                    'label' => $exam['label'],
                    'root' => $root,
                    'is_new' => true,
                ];
            }
        }

        $warnings = [];
        foreach ($groups as $group) {
            $entries = $group['entries'];
            $count = count($entries);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $entries[$i];
                    $b = $entries[$j];

                    // Yalnızca yeni eklenen/taşınan sınavın neden olduğu çakışmalar bildirilir
                    if (!$a['is_new'] && !$b['is_new']) {
                        continue;
                    }
                    if ($a['root'] && $a['root'] === $b['root']) {
                        continue;
                    }
                    $classA = (int) getClassFromSemesterNo($a['semester_no']);
                    $classB = (int) getClassFromSemesterNo($b['semester_no']);
                    if ($classA === $classB) {
                        continue;
                    }
                    if (!TimeHelper::isOverlapping($a['start'], $a['end'], $b['start'], $b['end'])) {
                        continue;
                    }

                    $programName = (new Program())->find($group['program_id'])?->name ?? '';
                    $dayName = self::DAYS[$group['day_index']] ?? 'Bu gün';
                    $warnings[] = trim($programName) . ": {$dayName} günü {$classA}. sınıf"
                        . ($a['label'] !== '' ? " ({$a['label']})" : '')
                        . " ve {$classB}. sınıf" . ($b['label'] !== '' ? " ({$b['label']})" : '')
                        . " sınavları aynı saatte çakışıyor ({$a['start']} - {$a['end']} / {$b['start']} - {$b['end']}).";
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
