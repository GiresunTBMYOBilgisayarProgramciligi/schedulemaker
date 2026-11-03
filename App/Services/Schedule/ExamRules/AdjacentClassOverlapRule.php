<?php

namespace App\Services\Schedule\ExamRules;

use App\Helpers\TimeHelper;

/**
 * Aynı programda farklı sınıf düzeylerinin (1-2, 1-3, 2-4, ...) sınavları zaman olarak çakışmamalıdır.
 *
 * Sınıf, yarıyıl numarasından hesaplanır (getClassFromSemesterNo). MYO'lar için katı bir kural olabilse de
 * fakültelerde zorunlu olmadığı için engelleyici değil, uyarıdır.
 * Aynı birleşik sınava (lesson_combinations, type=exam) ait dersler birbirinin çakışması sayılmaz.
 */
class AdjacentClassOverlapRule implements ExamWarningRule
{
    public function title(): string
    {
        return 'Farklı sınıf düzeylerinin sınavları çakışıyor';
    }

    public function description(): string
    {
        return 'Aynı programın farklı sınıflarındaki sınavların aynı saatte yapılması, iki sınıfın derslerini alan '
            . '(örneğin dersten kalan) öğrencilerin sınava girmesini engeller. Fakültelerde zorunlu olmadığı için '
            . 'bu kural yalnızca uyarıdır; sınavlardan birinin saatini değiştirmeniz önerilir.';
    }

    public function check(ExamRuleContext $context): array
    {
        $warnings = [];
        foreach ($this->groupEntries($context) as $group) {
            $entries = $group['entries'];
            $count = count($entries);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    [$a, $b] = [$entries[$i], $entries[$j]];

                    // Yalnızca yeni eklenen/taşınan sınavın neden olduğu çakışmalar bildirilir
                    if ((!$a['is_new'] && !$b['is_new']) || !ClassOverlap::conflicts($a, $b)) {
                        continue;
                    }

                    $warnings[] = trim($context->programName($group['program_id'])) . ': ' . TimeHelper::dayName($group['day_index'])
                        . ' günü ' . $this->describe($a) . ' ve ' . $this->describe($b)
                        . " sınavları aynı saatte çakışıyor ({$a['start']} - {$a['end']} / {$b['start']} - {$b['end']}).";
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Eklenecek sınavları ve etkiledikleri programların aynı gün/haftadaki mevcut sınavlarını
     * "program|gün|hafta" bazında toplar.
     *
     * @return array<string, array{program_id:int, day_index:int, entries:array}>
     */
    private function groupEntries(ExamRuleContext $context): array
    {
        $groups = [];
        foreach ($context->newExams() as $exam) {
            $dto = $exam['dto'];
            $root = $context->examRootId($exam['lesson']);

            foreach ($exam['programs'] as $program) {
                $key = "{$program['program_id']}|{$dto->dayIndex}|{$dto->weekIndex}";
                $groups[$key] ??= [
                    'program_id' => $program['program_id'],
                    'day_index' => $dto->dayIndex,
                    'entries' => array_map(
                        fn(array $item) => $item + ['is_new' => false],
                        $context->existingExams($program['program_id'], $exam['schedule'], $dto->dayIndex, $dto->weekIndex)
                    ),
                ];
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

        return $groups;
    }

    /**
     * "1. sınıf (BSP-141 Muhasebe I)" biçiminde sınav tanımı.
     */
    private function describe(array $entry): string
    {
        return ClassOverlap::classOf($entry) . '. sınıf' . ($entry['label'] !== '' ? " ({$entry['label']})" : '');
    }
}
