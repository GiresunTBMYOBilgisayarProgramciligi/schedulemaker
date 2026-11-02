<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Services\Schedule\ExamRules\DailyExamLimitRule;
use PDO;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Kayıtlı sınav programlarını analiz eder (yalnızca okuma).
 *
 * ExamRules altındaki esnek kurallar sınav eklenirken uyarı üretir; bu servis ise zaten kaydedilmiş
 * programlardaki ihlalleri raporlar. Sayfa (Sınav Analizleri) ve bin/ altındaki betikler aynı servisi kullanır.
 */
class ExamAnalysisService
{
    private const DAYS = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Aynı programda farklı sınıf düzeylerinin sınavlarından zamanı çakışanları bulur
     * (bkz. AdjacentClassOverlapRule). Sınav birleştirmesiyle (lesson_combinations, type=exam) bağlı
     * dersler aynı sınav sayılır, çakışma sayılmaz.
     *
     * @param int[]|null $programIds Verilirse yalnızca bu programlar; null ise tüm programlar
     * @return array{item_count:int, program_count:int, conflict_count:int, programs:array<int, array{program_id:int, program:string, unit:string, conflicts:array}>}
     */
    public function classOverlaps(ExamType $type, string $semester, string $academicYear, ?array $programIds = null): array
    {
        $items = $this->loadProgramItems($type, $semester, $academicYear, $programIds);
        $rootOf = $this->examRoots($semester, $academicYear);
        $lessons = $this->loadLessons($items);

        $byProgram = [];
        $itemCount = 0;
        foreach ($items as $item) {
            $item['root'] = $rootOf[$item['lesson_id']] ?? $item['lesson_id'];
            $item['class'] = (int) getClassFromSemesterNo($item['semester_no']);
            $byProgram[$item['program_id']][] = $item;
            $itemCount++;
        }

        $conflicts = [];
        foreach ($byProgram as $programId => $programItems) {
            $count = count($programItems);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    [$a, $b] = [$programItems[$i], $programItems[$j]];
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
                        'day' => self::DAYS[$a['day_index']] ?? (string) $a['day_index'],
                        'day_index' => $a['day_index'],
                        'week_index' => $a['week_index'],
                        'first' => $this->describe($a, $lessons),
                        'second' => $this->describe($b, $lessons),
                    ];
                }
            }
        }

        foreach ($conflicts as &$list) {
            usort($list, fn($x, $y) => [$x['day_index'], $x['first']['start']] <=> [$y['day_index'], $y['first']['start']]);
        }
        unset($list);

        return [
            'item_count' => $itemCount,
            'program_count' => count($byProgram),
            'conflict_count' => array_sum(array_map('count', $conflicts)),
            'programs' => $this->withProgramInfo($conflicts, 'conflicts'),
        ];
    }

    /**
     * Bir sınıfın (program + yarıyıl) aynı günde yapılabilecek en fazla sınav sayısını aşan günlerini bulur
     * (bkz. DailyExamLimitRule). Aynı saat dilimindeki sınavlar (gruplar, sınav birleştirmeleri) tek sayılır.
     *
     * @param int[]|null $programIds Verilirse yalnızca bu programlar; null ise tüm programlar
     * @param int|null $max Günlük sınır; verilmezse DailyExamLimitRule::MAX_EXAMS_PER_DAY
     * @return array{max:int, item_count:int, class_count:int, violation_count:int, programs:array<int, array{program_id:int, program:string, unit:string, violations:array}>}
     */
    public function dailyLimitViolations(ExamType $type, string $semester, string $academicYear, ?array $programIds = null, ?int $max = null): array
    {
        $max ??= DailyExamLimitRule::MAX_EXAMS_PER_DAY;
        $items = $this->loadProgramItems($type, $semester, $academicYear, $programIds);
        $lessons = $this->loadLessons($items);

        // "program|yarıyıl|gün|hafta" => ['meta' => item, 'slots' => saat dilimi => ders etiketleri]
        $groups = [];
        $classKeys = [];
        foreach ($items as $item) {
            $lesson = $lessons[$item['lesson_id']] ?? null;
            $label = $lesson ? $lesson['code'] . ' ' . $lesson['name'] . ($lesson['group_no'] ? " ({$lesson['group_no']}. grup)" : '') : '?';

            $key = "{$item['program_id']}|{$item['semester_no']}|{$item['day_index']}|{$item['week_index']}";
            $groups[$key]['meta'] = $item;
            $groups[$key]['slots']["{$item['start_time']}|{$item['end_time']}"][] = $label;
            $classKeys["{$item['program_id']}|{$item['semester_no']}"] = true;
        }

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
                'semester_no' => $meta['semester_no'],
                'class' => (int) getClassFromSemesterNo($meta['semester_no']),
                'day' => self::DAYS[$meta['day_index']] ?? (string) $meta['day_index'],
                'day_index' => $meta['day_index'],
                'week_index' => $meta['week_index'],
                'exam_count' => $count,
                'exams' => $exams,
            ];
        }

        foreach ($violations as &$list) {
            usort($list, fn($x, $y) => [$x['semester_no'], $x['day_index']] <=> [$y['semester_no'], $y['day_index']]);
        }
        unset($list);

        return [
            'max' => $max,
            'item_count' => count($items),
            'class_count' => count($classKeys),
            'violation_count' => array_sum(array_map('count', $violations)),
            'programs' => $this->withProgramInfo($violations, 'violations'),
        ];
    }

    /**
     * Programların sınav programı kayıtlarını (single/group) ders kimliğiyle birlikte getirir.
     *
     * @param int[]|null $programIds
     * @return array<int, array{program_id:int, semester_no:int, day_index:int, week_index:int, start_time:string, end_time:string, lesson_id:int}>
     */
    private function loadProgramItems(ExamType $type, string $semester, string $academicYear, ?array $programIds): array
    {
        if ($programIds !== null && $programIds === []) {
            return [];
        }

        $sql = "SELECT i.day_index, i.week_index, i.start_time, i.end_time, i.data, s.owner_id AS program_id, s.semester_no
                FROM schedules s JOIN schedule_items i ON i.schedule_id = s.id
                WHERE s.type = ? AND s.semester = ? AND s.academic_year = ? AND s.owner_type = ?
                  AND i.status IN ('single', 'group')";
        $params = [$type->value, $semester, $academicYear, OwnerType::PROGRAM->value];
        if ($programIds !== null) {
            $sql .= ' AND s.owner_id IN (' . implode(',', array_fill(0, count($programIds), '?')) . ')';
            array_push($params, ...array_map('intval', $programIds));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $data = @unserialize($row['data'], ['allowed_classes' => false]);
            $items[] = [
                'program_id' => (int) $row['program_id'],
                'semester_no' => (int) $row['semester_no'],
                'day_index' => (int) $row['day_index'],
                'week_index' => (int) $row['week_index'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'lesson_id' => (int) ($data[0]['lesson_id'] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * Sınav birleştirmesi: alt ders kimliği => üst ders kimliği (aynı sınav sayılır).
     *
     * @return array<int, int>
     */
    private function examRoots(string $semester, string $academicYear): array
    {
        $stmt = $this->db->prepare("SELECT parent_lesson_id, child_lesson_id FROM lesson_combinations WHERE type = 'exam' AND semester = ? AND academic_year = ?");
        $stmt->execute([$semester, $academicYear]);

        $roots = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roots[(int) $row['child_lesson_id']] = (int) $row['parent_lesson_id'];
        }

        return $roots;
    }

    /**
     * @param array[] $items loadProgramItems() çıktısı
     * @return array<int, array{id:int, code:string, name:string, group_no:int, size:int}>
     */
    private function loadLessons(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_column($items, 'lesson_id'))));
        if ($ids === []) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT id, code, name, group_no, size FROM lessons WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);

        $lessons = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lessons[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'group_no' => (int) $row['group_no'],
                'size' => (int) $row['size'],
            ];
        }

        return $lessons;
    }

    private function describe(array $item, array $lessons): array
    {
        $lesson = $lessons[$item['lesson_id']] ?? null;

        return [
            'class' => $item['class'],
            'lesson_id' => $item['lesson_id'],
            'code' => $lesson['code'] ?? '?',
            'name' => $lesson['name'] ?? '?',
            'group_no' => $lesson['group_no'] ?? 0,
            'size' => $lesson['size'] ?? 0,
            'start' => $item['start_time'],
            'end' => $item['end_time'],
        ];
    }

    /**
     * Program kimliğiyle gruplanmış sonuçlara program ve birim adını ekler; en çok ihlali olan program başa gelir.
     *
     * @param array<int, array> $byProgram program kimliği => ihlal listesi
     * @return array<int, array>
     */
    private function withProgramInfo(array $byProgram, string $listKey): array
    {
        if ($byProgram === []) {
            return [];
        }

        $ids = array_keys($byProgram);
        $stmt = $this->db->prepare(
            'SELECT p.id, p.name, u.name AS unit FROM programs p
             LEFT JOIN departments d ON d.id = p.department_id
             LEFT JOIN units u ON u.id = d.unit_id
             WHERE p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $stmt->execute($ids);
        $info = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), null, 'id');

        $result = [];
        foreach ($byProgram as $programId => $list) {
            $result[] = [
                'program_id' => (int) $programId,
                'program' => (string) ($info[$programId]['name'] ?? ''),
                'unit' => (string) ($info[$programId]['unit'] ?? ''),
                $listKey => $list,
            ];
        }
        usort($result, fn($x, $y) => count($y[$listKey]) <=> count($x[$listKey]) ?: strcmp($x['program'], $y['program']));

        return $result;
    }
}
