<?php

namespace App\Services\Schedule\ExamRules;

use App\DTOs\ScheduleItemDTO;
use App\Services\Schedule\ExamScheduleService;
use Exception;

/**
 * Sınav programındaki esnek (uyarı niteliğindeki) kuralları çalıştırır ve uyarıları birleştirir.
 *
 * Kurallar engelleyici değildir; kullanıcı onaylarsa kayıt yapılır. Engelleyici kurallar
 * ConflictService / ConflictResolver tarafından denetlenir.
 */
class ExamWarningService
{
    /** @var ExamWarningRule[] */
    private array $rules;

    /**
     * @param ExamWarningRule[]|null $rules Verilmezse defaultRules() kullanılır
     */
    public function __construct(?array $rules = null, private readonly ?ExamScheduleService $examService = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /**
     * Sistemde etkin olan esnek sınav kuralları. Yeni kurallar buraya eklenir.
     *
     * @return ExamWarningRule[]
     */
    public static function defaultRules(): array
    {
        return [
            new DailyExamLimitRule(),
            new AdjacentClassOverlapRule(),
        ];
    }

    /**
     * @param ScheduleItemDTO[] $dtos Eklenecek item DTO'ları
     * @param ScheduleItemDTO[] $deletedDtos Aynı işlemde silinecek item DTO'ları (taşıma/düzenleme)
     * @return string[] Uyarı mesajları (boşsa sorun yok)
     * @throws Exception
     */
    public function check(array $dtos, array $deletedDtos = []): array
    {
        $context = new ExamRuleContext($dtos, $deletedDtos, $this->examService ?? new ExamScheduleService());

        $warnings = [];
        foreach ($this->rules as $rule) {
            $warnings = array_merge($warnings, $rule->check($context));
        }

        return $warnings;
    }
}
