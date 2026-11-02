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

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Bu betik yalnızca CLI (komut satırı) üzerinden çalıştırılabilir.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Enums\ExamType;
use App\Services\Schedule\ExamAnalysisService;
use Dotenv\Dotenv;
use function App\Helpers\getSettingValue;

if (file_exists(dirname(__DIR__) . '/App/.env')) {
    Dotenv::createImmutable(dirname(__DIR__) . '/App')->safeLoad();
}

$options = getopt('', ['type::', 'semester::', 'year::', 'program::', 'max::', 'format::', 'help']);
if (isset($options['help'])) {
    $source = file_get_contents(__FILE__);
    echo trim(preg_replace('/^\s*\* ?/m', '', substr($source, 8, strpos($source, '*/') - 8))) . "\n";
    exit(0);
}

try {
    $examType = ExamType::fromAlias($options['type'] ?? 'vize');
    if (!$examType) {
        throw new InvalidArgumentException("Geçersiz sınav tipi: {$options['type']} (vize, final veya but olmalı)");
    }
    $semester = $options['semester'] ?? getSettingValue('semester');
    $academicYear = $options['year'] ?? getSettingValue('academic_year');
    $programIds = isset($options['program']) ? [(int) $options['program']] : null;
    $max = isset($options['max']) ? (int) $options['max'] : null;
    $outputFormat = $options['format'] ?? 'text';
    if (!in_array($outputFormat, ['text', 'json'], true)) {
        throw new InvalidArgumentException("Geçersiz format: {$outputFormat} (text veya json olmalı)");
    }

    $result = (new ExamAnalysisService())->dailyLimitViolations($examType, $semester, $academicYear, $programIds, $max);

    if ($outputFormat === 'json') {
        echo json_encode(
            ['type' => $examType->value, 'semester' => $semester, 'academic_year' => $academicYear] + $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n";
        exit($result['violation_count'] > 0 ? 1 : 0);
    }

    echo "{$examType->label()} - {$semester} {$academicYear} (günlük sınır: {$result['max']})\n";
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

    exit($result['violation_count'] > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(2);
}
