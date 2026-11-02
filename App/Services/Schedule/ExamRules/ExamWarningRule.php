<?php

namespace App\Services\Schedule\ExamRules;

/**
 * Sınav programı düzenlenirken kullanıcıya gösterilen esnek (engelleyici olmayan) kural.
 *
 * Yeni bir kural eklemek için bu arayüzü uygulayan bir sınıf yazılır ve
 * ExamWarningService::defaultRules() listesine eklenir.
 */
interface ExamWarningRule
{
    /**
     * Uyarı penceresinde kural grubunun başlığı (kısa, ihlali anlatan cümle).
     */
    public function title(): string;

    /**
     * Kuralın ne olduğunu ve ihlalin nasıl giderilebileceğini açıklayan metin.
     */
    public function description(): string;

    /**
     * Eklenecek/taşınacak sınavlar sonrası kuralın ihlal edildiği durumlar için uyarı mesajlarını döner.
     *
     * @return string[] Uyarı mesajları (boşsa ihlal yok)
     */
    public function check(ExamRuleContext $context): array;
}
