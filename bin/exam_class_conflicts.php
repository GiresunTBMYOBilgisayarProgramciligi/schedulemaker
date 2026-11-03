<?php

/**
 * ScheduleMaker - Sınav Programı Sınıf Düzeyi Çakışma Raporu
 *
 * Kayıtlı sınav programlarında, aynı programın farklı sınıf düzeylerindeki (1-2, 1-3, 2-4 ...)
 * sınavlarından zamanı çakışanları listeler (bkz. AdjacentClassOverlapRule). Yalnızca okuma yapar,
 * veritabanında değişiklik yapmaz. Sınav birleştirmesiyle (lesson_combinations, type=exam) bağlı
 * dersler aynı sınav sayıldığı için çakışma sayılmaz.
 *
 * Kullanım:
 *   php bin/exam_class_conflicts.php [--type=vize|final|but] [--semester=Güz] [--year="2026 - 2027"]
 *                                    [--program=<id>] [--format=text|json]
 *
 * Seçenek verilmezse tip = vize, dönem ve akademik yıl = sistem ayarlarındaki geçerli değerler.
 * Çakışma bulunursa çıkış kodu 1, bulunmazsa 0, hata olursa 2 olur.
 */

require __DIR__ . '/lib/exam_report.php';

use App\Services\Schedule\ExamAnalysisService;

runExamReport(
    __FILE__,
    [],
    fn($filter, $programIds) => (new ExamAnalysisService())->classOverlaps($filter->examType(), $filter->semester, $filter->academic_year, $programIds),
    function (array $result, $filter): void {
        $describe = static fn(array $d) => sprintf(
            '%d. sınıf %s %s%s [%s-%s] mevcut %d',
            $d['class'], $d['code'], $d['name'], $d['group_no'] ? " ({$d['group_no']}. grup)" : '',
            substr($d['start'], 0, 5), substr($d['end'], 0, 5), $d['size']
        );

        echo "{$filter->examType()->label()} - {$filter->semester} {$filter->academic_year}\n";
        echo "Program: {$result['program_count']}, sınav kaydı: {$result['item_count']}, sınıf çakışması: {$result['conflict_count']}, etkilenen program: " . count($result['programs']) . "\n";
        foreach ($result['programs'] as $program) {
            echo "\n## {$program['unit']} / {$program['program']} (id {$program['program_id']}) - " . count($program['conflicts']) . " çakışma\n";
            foreach ($program['conflicts'] as $c) {
                echo "- {$c['day']}: " . $describe($c['first']) . '  <->  ' . $describe($c['second']) . "\n";
            }
        }
    },
    'conflict_count'
);
