<?php

/**
 * ScheduleMaker - Sınav Programı Günlük Sınav Sınırı Raporu
 *
 * Kayıtlı sınav programlarında bir sınıfın (program + yarıyıl) aynı günde yapılabilecek en fazla sınav
 * sayısını (DailyExamLimitRule::MAX_EXAMS_PER_DAY) aşan günlerini listeler. Yalnızca okuma yapar,
 * veritabanında değişiklik yapmaz. Uygulamadaki uyarı kuralıyla aynı şekilde, aynı saat dilimindeki
 * sınavlar (gruplar, sınav birleştirmeleri) tek sınav sayılır.
 *
 * Kullanım:
 *   php bin/exam_daily_limit.php [--type=vize|final|but] [--semester=Güz] [--year="2026 - 2027"]
 *                                [--program=<id>] [--max=<sayı>] [--format=text|json]
 *
 * Seçenek verilmezse tip = vize, dönem ve akademik yıl = sistem ayarlarındaki geçerli değerler,
 * sınır = DailyExamLimitRule::MAX_EXAMS_PER_DAY.
 * Sınırı aşan gün bulunursa çıkış kodu 1, bulunmazsa 0, hata olursa 2 olur.
 */

require __DIR__ . '/lib/exam_report.php';

use App\Services\Schedule\ExamAnalysisService;

runExamReport(
    __FILE__,
    ['max::'],
    fn($filter, $programIds, $options) => (new ExamAnalysisService())->dailyLimitViolations(
        $filter->examType(), $filter->semester, $filter->academic_year, $programIds, isset($options['max']) ? (int) $options['max'] : null
    ),
    function (array $result, $filter): void {
        echo "{$filter->examType()->label()} - {$filter->semester} {$filter->academic_year} (günlük sınır: {$result['max']})\n";
        echo "Sınıf: {$result['class_count']}, sınav kaydı: {$result['item_count']}, sınırı aşan gün: {$result['violation_count']}, etkilenen program: " . count($result['programs']) . "\n";
        foreach ($result['programs'] as $program) {
            echo "\n## {$program['unit']} / {$program['program']} (id {$program['program_id']}) - " . count($program['violations']) . " gün\n";
            foreach ($program['violations'] as $v) {
                echo "- {$v['class']}. sınıf, {$v['day']}: {$v['exam_count']} sınav\n";
                foreach ($v['exams'] as $exam) {
                    echo '    ' . substr($exam['start'], 0, 5) . '-' . substr($exam['end'], 0, 5) . '  ' . implode(' + ', $exam['lessons']) . "\n";
                }
            }
        }
    },
    'violation_count'
);
