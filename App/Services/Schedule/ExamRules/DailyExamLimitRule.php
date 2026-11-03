<?php

namespace App\Services\Schedule\ExamRules;

use App\Helpers\TimeHelper;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Yönetmeliğe göre bir programda (program_id + semester_no) bir günde en fazla MAX_EXAMS_PER_DAY sınav yapılabilir.
 *
 * Aynı saat diliminde olan sınavlar (gruplar, sınav birleştirmeleri) tek sınav sayılır.
 */
class DailyExamLimitRule implements ExamWarningRule
{
    public const MAX_EXAMS_PER_DAY = 2;

    public function title(): string
    {
        return 'Günlük sınav sınırı aşılıyor';
    }

    public function description(): string
    {
        return 'Yönetmeliğe göre bir sınıfın bir günde en fazla ' . self::MAX_EXAMS_PER_DAY . ' sınavı olabilir. '
            . 'Aynı saat dilimindeki sınavlar (gruplar, birleştirilmiş sınavlar) tek sınav sayılır. '
            . 'Sınavlardan birini başka bir güne taşımanız önerilir.';
    }

    public function check(ExamRuleContext $context): array
    {
        // "program|yarıyıl|gün|hafta" => ['slots' => [slotKey => etiket], ...]
        $targets = [];

        foreach ($context->newExams() as $exam) {
            $dto = $exam['dto'];
            $newSlot = "{$dto->startTime}|{$dto->endTime}";

            foreach ($exam['programs'] as $program) {
                $key = "{$program['program_id']}|{$program['semester_no']}|{$dto->dayIndex}|{$dto->weekIndex}";
                if (!isset($targets[$key])) {
                    $slots = [];
                    $existing = $context->existingExams($program['program_id'], $exam['schedule'], $dto->dayIndex, $dto->weekIndex, $program['semester_no']);
                    foreach ($existing as $item) {
                        $slots["{$item['start']}|{$item['end']}"] = $item['label'];
                    }

                    $targets[$key] = [
                        'program_id' => $program['program_id'],
                        'semester_no' => $program['semester_no'],
                        'day_index' => $dto->dayIndex,
                        'slots' => $slots,
                    ];
                }
                $targets[$key]['slots'][$newSlot] = $exam['label'];
            }
        }

        $warnings = [];
        foreach ($targets as $target) {
            $count = count($target['slots']);
            if ($count <= self::MAX_EXAMS_PER_DAY) {
                continue;
            }
            $programName = $context->programName($target['program_id']);
            $classLabel = $target['semester_no'] ? getClassFromSemesterNo($target['semester_no']) . ". Sınıf" : '';
            $dayName = TimeHelper::dayName($target['day_index']);
            $names = implode(', ', array_filter($target['slots']));
            $warnings[] = trim("{$programName} {$classLabel}") . ": {$dayName} günü {$count} sınav olacak"
                . ($names !== '' ? " ({$names})" : '')
                . ". Yönetmeliğe göre bir günde en fazla " . self::MAX_EXAMS_PER_DAY . " sınav yapılabilir.";
        }

        return $warnings;
    }
}
