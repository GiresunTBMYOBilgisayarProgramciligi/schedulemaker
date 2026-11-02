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

$options = getopt('', ['type::', 'semester::', 'year::', 'program::', 'format::', 'help']);
if (isset($options['help'])) {
    echo trim(preg_replace('/^\s*\* ?/m', '', substr(file_get_contents(__FILE__), 8, strpos(file_get_contents(__FILE__), '*/') - 8))) . "\n";
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
    $outputFormat = $options['format'] ?? 'text';
    if (!in_array($outputFormat, ['text', 'json'], true)) {
        throw new InvalidArgumentException("Geçersiz format: {$outputFormat} (text veya json olmalı)");
    }

    $result = (new ExamAnalysisService())->classOverlaps($examType, $semester, $academicYear, $programIds);

    if ($outputFormat === 'json') {
        echo json_encode(
            ['type' => $examType->value, 'semester' => $semester, 'academic_year' => $academicYear] + $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n";
        exit($result['conflict_count'] > 0 ? 1 : 0);
    }

    $describe = static fn(array $d) => sprintf(
        '%d. sınıf %s %s%s [%s-%s] mevcut %d',
        $d['class'], $d['code'], $d['name'], $d['group_no'] ? " ({$d['group_no']}. grup)" : '',
        substr($d['start'], 0, 5), substr($d['end'], 0, 5), $d['size']
    );

    echo "{$examType->label()} - {$semester} {$academicYear}\n";
    echo "Program: {$result['program_count']}, sınav kaydı: {$result['item_count']}, sınıf çakışması: {$result['conflict_count']}, etkilenen program: " . count($result['programs']) . "\n";
    foreach ($result['programs'] as $program) {
        echo "\n## {$program['unit']} / {$program['program']} (id {$program['program_id']}) - " . count($program['conflicts']) . " çakışma\n";
        foreach ($program['conflicts'] as $c) {
            echo "- {$c['day']}: " . $describe($c['first']) . '  <->  ' . $describe($c['second']) . "\n";
        }
    }

    exit($result['conflict_count'] > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(2);
}
