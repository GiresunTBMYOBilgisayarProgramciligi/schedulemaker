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

use App\Core\Database;
use App\Enums\ExamType;
use Dotenv\Dotenv;
use function App\Helpers\getClassFromSemesterNo;
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
    $programFilter = isset($options['program']) ? (int) $options['program'] : null;
    $format = $options['format'] ?? 'text';
    if (!in_array($format, ['text', 'json'], true)) {
        throw new InvalidArgumentException("Geçersiz format: {$format} (text veya json olmalı)");
    }

    $pdo = Database::getConnection();

    // Sınav birleştirmesi: alt ders => üst ders (aynı sınav sayılır)
    $stmt = $pdo->prepare("SELECT parent_lesson_id, child_lesson_id FROM lesson_combinations WHERE type = 'exam' AND semester = ? AND academic_year = ?");
    $stmt->execute([$semester, $academicYear]);
    $rootOf = array_column($stmt->fetchAll(), 'parent_lesson_id', 'child_lesson_id');

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

    $byProgram = [];
    $totalItems = 0;
    foreach ($stmt->fetchAll() as $row) {
        $data = @unserialize($row['data'], ['allowed_classes' => false]);
        $lessonId = (int) ($data[0]['lesson_id'] ?? 0);
        $row['lesson_id'] = $lessonId;
        $row['root'] = (int) ($rootOf[$lessonId] ?? $lessonId);
        $row['class'] = (int) getClassFromSemesterNo($row['semester_no']);
        $byProgram[$row['program_id']][] = $row;
        $totalItems++;
    }

    $lessons = [];
    foreach ($pdo->query('SELECT id, code, name, group_no, size FROM lessons') as $lesson) {
        $lessons[$lesson['id']] = $lesson;
    }
    $programs = [];
    foreach ($pdo->query('SELECT p.id, p.name, u.name AS unit FROM programs p JOIN departments d ON d.id = p.department_id JOIN units u ON u.id = d.unit_id') as $program) {
        $programs[$program['id']] = $program;
    }

    $days = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
    $describe = function (array $item) use ($lessons): array {
        $lesson = $lessons[$item['lesson_id']] ?? null;
        return [
            'class' => $item['class'],
            'lesson_id' => $item['lesson_id'],
            'code' => $lesson['code'] ?? '?',
            'name' => $lesson['name'] ?? '?',
            'group_no' => (int) ($lesson['group_no'] ?? 0),
            'size' => (int) ($lesson['size'] ?? 0),
            'start' => $item['start_time'],
            'end' => $item['end_time'],
        ];
    };

    $conflicts = [];
    foreach ($byProgram as $programId => $items) {
        $count = count($items);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                [$a, $b] = [$items[$i], $items[$j]];
                if ($a['class'] === $b['class'] || $a['root'] === $b['root']) {
                    continue;
                }
                if ($a['day_index'] !== $b['day_index'] || $a['week_index'] !== $b['week_index']) {
                    continue;
                }
                if (strtotime($a['end_time']) <= strtotime($b['start_time']) || strtotime($a['start_time']) >= strtotime($b['end_time'])) {
                    continue;
                }
                if ($a['class'] > $b['class']) {
                    [$a, $b] = [$b, $a];
                }
                $conflicts[$programId][] = [
                    'day' => $days[$a['day_index']] ?? (string) $a['day_index'],
                    'day_index' => (int) $a['day_index'],
                    'week_index' => (int) $a['week_index'],
                    'first' => $describe($a),
                    'second' => $describe($b),
                ];
            }
        }
    }

    uasort($conflicts, fn($x, $y) => count($y) <=> count($x));
    $conflictTotal = array_sum(array_map('count', $conflicts));

    if ($format === 'json') {
        $report = [
            'type' => $examType->value, 'semester' => $semester, 'academic_year' => $academicYear,
            'program_count' => count($byProgram), 'item_count' => $totalItems,
            'conflict_count' => $conflictTotal, 'programs' => [],
        ];
        foreach ($conflicts as $programId => $list) {
            $report['programs'][] = ['program_id' => $programId, 'unit' => $programs[$programId]['unit'] ?? '', 'program' => $programs[$programId]['name'] ?? '', 'conflicts' => $list];
        }
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        exit($conflictTotal > 0 ? 1 : 0);
    }

    $format = static fn(array $d) => sprintf(
        '%d. sınıf %s %s%s [%s-%s] mevcut %d',
        $d['class'], $d['code'], $d['name'], $d['group_no'] ? " ({$d['group_no']}. grup)" : '',
        substr($d['start'], 0, 5), substr($d['end'], 0, 5), $d['size']
    );

    echo "{$examType->label()} - {$semester} {$academicYear}\n";
    echo "Program: " . count($byProgram) . ", sınav kaydı: {$totalItems}, sınıf çakışması: {$conflictTotal}, etkilenen program: " . count($conflicts) . "\n";
    foreach ($conflicts as $programId => $list) {
        usort($list, fn($x, $y) => [$x['day_index'], $x['first']['start']] <=> [$y['day_index'], $y['first']['start']]);
        $program = $programs[$programId] ?? ['unit' => '?', 'name' => '?'];
        echo "\n## {$program['unit']} / {$program['name']} (id {$programId}) - " . count($list) . " çakışma\n";
        foreach ($list as $c) {
            echo "- {$c['day']}: " . $format($c['first']) . '  <->  ' . $format($c['second']) . "\n";
        }
    }

    exit($conflictTotal > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
    exit(2);
}
