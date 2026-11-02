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

use App\Core\Database;
use App\Enums\ExamType;
use App\Services\Schedule\ExamRules\DailyExamLimitRule;
use Dotenv\Dotenv;
use function App\Helpers\getClassFromSemesterNo;
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
    $programFilter = isset($options['program']) ? (int) $options['program'] : null;
    $max = isset($options['max']) ? (int) $options['max'] : DailyExamLimitRule::MAX_EXAMS_PER_DAY;
    $outputFormat = $options['format'] ?? 'text';
    if (!in_array($outputFormat, ['text', 'json'], true)) {
        throw new InvalidArgumentException("Geçersiz format: {$outputFormat} (text veya json olmalı)");
    }

    $pdo = Database::getConnection();

    $sql = "SELECT i.day_index, i.week_index, i.start_time, i.end_time, i.data, s.owner_id AS program_id, s.semester_no
            FROM schedules s JOIN schedule_items i ON i.schedule_id = s.id
            WHERE s.type = ? AND s.semester = ? AND s.academic_year = ? AND s.owner_type = 'program'
              AND i.status IN ('single', 'group')";
    $params = [$examType->value, $semester, $academicYear];
    if ($programFilter) {
        $sql .= ' AND s.owner_id = ?';
        $params[] = $programFilter;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $lessons = [];
    foreach ($pdo->query('SELECT id, code, name, group_no FROM lessons') as $lesson) {
        $lessons[$lesson['id']] = $lesson;
    }
    $programs = [];
    foreach ($pdo->query('SELECT p.id, p.name, u.name AS unit FROM programs p JOIN departments d ON d.id = p.department_id JOIN units u ON u.id = d.unit_id') as $program) {
        $programs[$program['id']] = $program;
    }

    // "program|yarıyıl|gün|hafta" => saat dilimi => ders etiketleri
    $groups = [];
    $totalItems = 0;
    $classKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $data = @unserialize($row['data'], ['allowed_classes' => false]);
        $lesson = $lessons[(int) ($data[0]['lesson_id'] ?? 0)] ?? null;
        $label = $lesson ? "{$lesson['code']} {$lesson['name']}" . ($lesson['group_no'] ? " ({$lesson['group_no']}. grup)" : '') : '?';

        $groupKey = "{$row['program_id']}|{$row['semester_no']}|{$row['day_index']}|{$row['week_index']}";
        $groups[$groupKey]['meta'] = $row;
        $groups[$groupKey]['slots']["{$row['start_time']}|{$row['end_time']}"][] = $label;
        $classKeys["{$row['program_id']}|{$row['semester_no']}"] = true;
        $totalItems++;
    }

    $days = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
    $violations = [];
    foreach ($groups as $group) {
        $count = count($group['slots']);
        if ($count <= $max) {
            continue;
        }
        $meta = $group['meta'];
        ksort($group['slots']);
        $exams = [];
        foreach ($group['slots'] as $slot => $labels) {
            [$start, $end] = explode('|', $slot);
            $exams[] = ['start' => $start, 'end' => $end, 'lessons' => array_values(array_unique($labels))];
        }
        $violations[$meta['program_id']][] = [
            'semester_no' => (int) $meta['semester_no'],
            'class' => (int) getClassFromSemesterNo($meta['semester_no']),
            'day' => $days[$meta['day_index']] ?? (string) $meta['day_index'],
            'day_index' => (int) $meta['day_index'],
            'week_index' => (int) $meta['week_index'],
            'exam_count' => $count,
            'exams' => $exams,
        ];
    }

    uasort($violations, fn($x, $y) => count($y) <=> count($x));
    $violationTotal = array_sum(array_map('count', $violations));

    if ($outputFormat === 'json') {
        $report = [
            'type' => $examType->value, 'semester' => $semester, 'academic_year' => $academicYear,
            'max_exams_per_day' => $max, 'class_count' => count($classKeys), 'item_count' => $totalItems,
            'violation_count' => $violationTotal, 'programs' => [],
        ];
        foreach ($violations as $programId => $list) {
            $report['programs'][] = ['program_id' => $programId, 'unit' => $programs[$programId]['unit'] ?? '', 'program' => $programs[$programId]['name'] ?? '', 'violations' => $list];
        }
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        exit($violationTotal > 0 ? 1 : 0);
    }

    echo "{$examType->label()} - {$semester} {$academicYear} (günlük sınır: {$max})\n";
    echo "Sınıf: " . count($classKeys) . ", sınav kaydı: {$totalItems}, sınırı aşan gün: {$violationTotal}, etkilenen program: " . count($violations) . "\n";
    foreach ($violations as $programId => $list) {
        usort($list, fn($x, $y) => [$x['semester_no'], $x['day_index']] <=> [$y['semester_no'], $y['day_index']]);
        $program = $programs[$programId] ?? ['unit' => '?', 'name' => '?'];
        echo "\n## {$program['unit']} / {$program['name']} (id {$programId}) - " . count($list) . " gün\n";
        foreach ($list as $v) {
            echo "- {$v['class']}. sınıf, {$v['day']}: {$v['exam_count']} sınav\n";
            foreach ($v['exams'] as $exam) {
                echo '    ' . substr($exam['start'], 0, 5) . '-' . substr($exam['end'], 0, 5) . '  ' . implode(' + ', $exam['lessons']) . "\n";
            }
        }
    }

    exit($violationTotal > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(2);
}
