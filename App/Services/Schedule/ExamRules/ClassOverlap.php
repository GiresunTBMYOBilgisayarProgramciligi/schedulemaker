<?php

namespace App\Services\Schedule\ExamRules;

use App\Helpers\TimeHelper;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Aynı programın aynı gün ve haftadaki iki sınavının sınıf düzeyi çakışması oluşturup oluşturmadığını belirler.
 * Hem sınav eklenirken uyarı üreten kural (AdjacentClassOverlapRule) hem de kayıtlı programları analiz eden
 * servis (ExamAnalysisService) aynı ölçütü kullanır.
 */
final class ClassOverlap
{
    /**
     * Sınıf düzeyleri farklı, aynı sınava ait olmayan ve süreleri kesişen iki sınav çakışır.
     *
     * @param array{semester_no:int, start:string, end:string, root:int} $a
     * @param array{semester_no:int, start:string, end:string, root:int} $b
     *        root: Sınav birleştirmesiyle bağlı dersler için ortak üst ders kimliği (aynı sınav sayılır)
     */
    public static function conflicts(array $a, array $b): bool
    {
        if ($a['root'] && $a['root'] === $b['root']) {
            return false;
        }

        return self::classOf($a) !== self::classOf($b)
            && TimeHelper::isOverlapping($a['start'], $a['end'], $b['start'], $b['end']);
    }

    /**
     * Sınavın sınıf düzeyi (yarıyıl 1-2 = 1. sınıf, 3-4 = 2. sınıf ...).
     *
     * @param array{semester_no:int} $exam
     */
    public static function classOf(array $exam): int
    {
        return (int) getClassFromSemesterNo($exam['semester_no']);
    }
}
