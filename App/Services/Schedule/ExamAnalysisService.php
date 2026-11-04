<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\Enums\ExamType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Models\Lesson;
use App\Helpers\TimeHelper;
use App\Services\Schedule\ExamRules\ClassOverlap;
use App\Services\Schedule\ExamRules\DailyExamLimitRule;
use PDO;

/**
 * Kayıtlı sınav programlarını analiz eder (yalnızca okuma).
 *
 * ExamRules altındaki esnek kurallar sınav eklenirken uyarı üretir; bu servis ise zaten kaydedilmiş
 * programlardaki ihlalleri raporlar. Sayfa (Sınav Analizleri) ve bin/ altındaki betikler aynı servisi kullanır.
 */
class ExamAnalysisService
{
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
        $lessons = $this->lessonsById(array_column($items, 'lesson_id'));

        // Çakışma yalnızca aynı programın aynı gün ve haftadaki sınavları arasında aranır
        $groups = [];
        $programCount = [];
        foreach ($items as $item) {
            $item['root'] = $rootOf[$item['lesson_id']] ?? $item['lesson_id'];
            $groups["{$item['program_id']}|{$item['day_index']}|{$item['week_index']}"][] = $item;
            $programCount[$item['program_id']] = true;
        }

        $conflicts = [];
        foreach ($groups as $groupItems) {
            $count = count($groupItems);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    [$a, $b] = [$groupItems[$i], $groupItems[$j]];
                    if (!ClassOverlap::conflicts($a, $b)) {
                        continue;
                    }
                    if (ClassOverlap::classOf($a) > ClassOverlap::classOf($b)) {
                        [$a, $b] = [$b, $a];
                    }
                    $conflicts[$a['program_id']][] = [
                        'day' => TimeHelper::dayName($a['day_index'], (string) $a['day_index']),
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
            'item_count' => count($items),
            'program_count' => count($programCount),
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
        $lessons = $this->lessonsById(array_column($items, 'lesson_id'));

        // "program|yarıyıl|gün|hafta" => ['meta' => item, 'slots' => saat dilimi => ders etiketleri]
        $groups = [];
        $classKeys = [];
        foreach ($items as $item) {
            $lesson = $lessons[$item['lesson_id']] ?? null;
            $label = $lesson ? $lesson['code'] . ' ' . $lesson['name'] . ($lesson['group_no'] ? " ({$lesson['group_no']}. grup)" : '') : '?';

            $key = "{$item['program_id']}|{$item['semester_no']}|{$item['day_index']}|{$item['week_index']}";
            $groups[$key]['meta'] = $item;
            $groups[$key]['slots']["{$item['start']}|{$item['end']}"][] = $label;
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
                'class' => ClassOverlap::classOf($meta),
                'day' => TimeHelper::dayName($meta['day_index'], (string) $meta['day_index']),
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
     * Sınav programı tamamlanmamış dersleri bulur. Tamamlanma ölçütü Lesson::IsScheduleComplete'tir: dersin toplam sınav
     * mevcudu (aynı kodlu gruplar ve sınav birleştirmesiyle bağlı dersler dahil) ile sınavın yapıldığı derslik
     * programlarına yerleşen sınav kapasitesi karşılaştırılır; "kalan mevcudu yok say" işaretli sınavlar tamamlanmış sayılır.
     *
     * Sınav birleştirmesiyle başka bir dersin sınavına bağlanan dersler ayrı sayılmaz, üst dersin içinde değerlendirilir.
     * Aynı koda sahip gruplar tek ders olarak listelenir. Staj dersleri dahil değildir.
     *
     * @param int[]|null $programIds Verilirse yalnızca bu programların dersleri; null ise tüm programlar
     * @return array{lesson_count:int, incomplete_count:int, unplaced_count:int, partial_count:int, programs:array<int, array{program_id:int, program:string, unit:string, lessons:array}>}
     */
    public function incompleteLessons(ExamType $type, string $semester, string $academicYear, ?array $programIds = null): array
    {
        $lecturers = $this->lecturersByLesson($semester, $academicYear);

        $lessonCount = 0;
        $counts = ['unplaced' => 0, 'partial' => 0];
        $byProgram = [];
        foreach ($this->examLessons(array_keys($lecturers), $programIds, $semester, $academicYear) as $lesson) {
            $lessonCount++;
            if ($lesson->IsScheduleComplete($type->value, $semester, $academicYear)) {
                continue;
            }

            $row = $this->incompleteLessonRow($lesson, $lecturers, $semester, $academicYear);
            $counts[$row['status']]++;
            $byProgram[(int) $lesson->program_id][] = $row;
        }

        foreach ($byProgram as &$list) {
            usort($list, fn($x, $y) => [$x['semester_no'], $x['code']] <=> [$y['semester_no'], $y['code']]);
        }
        unset($list);

        return [
            'lesson_count' => $lessonCount,
            'incomplete_count' => array_sum($counts),
            'unplaced_count' => $counts['unplaced'],
            'partial_count' => $counts['partial'],
            'programs' => $this->withProgramInfo($byProgram, 'lessons'),
        ];
    }

    /**
     * Dönemde ataması olan derslerin öğretim elemanlarını döner.
     *
     * @return array<int, string[]> ders kimliği => öğretim elemanı adları
     */
    private function lecturersByLesson(string $semester, string $academicYear): array
    {
        $stmt = $this->db->prepare(
            "SELECT la.lesson_id, u.title, u.name, u.last_name
             FROM lesson_assignments la LEFT JOIN users u ON u.id = la.lecturer_id
             WHERE la.semester = ? AND la.academic_year = ?"
        );
        $stmt->execute([$semester, $academicYear]);

        $lecturers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lecturers[(int) $row['lesson_id']][] = trim($row['title'] . ' ' . $row['name'] . ' ' . $row['last_name']);
        }

        return $lecturers;
    }

    /**
     * Sınav programı hesabına girecek dersleri döner: staj olmayan, verilen kimliklerdeki dersler arasından
     * sınav birleştirmesiyle başka bir dersin sınavına bağlı olmayanlar (üst dersin içinde değerlendirilir) ve
     * aynı koda sahip grupların yalnızca ilki (IsScheduleComplete tüm grupları birlikte değerlendirir).
     *
     * @param int[] $lessonIds
     * @param int[]|null $programIds
     * @return Lesson[]
     */
    private function examLessons(array $lessonIds, ?array $programIds, string $semester, string $academicYear): array
    {
        if ($lessonIds === [] || $programIds === []) {
            return [];
        }

        $filters = ['id' => ['in' => $lessonIds], '!type' => LessonType::INTERNSHIP->value];
        if ($programIds !== null) {
            $filters['program_id'] = ['in' => array_map('intval', $programIds)];
        }

        $seenGroups = [];
        $lessons = [];
        /** @var Lesson $lesson */
        foreach ((new Lesson())->get()->where($filters)->all() as $lesson) {
            if ($lesson->getExamRootLessonId($semester, $academicYear) !== (int) $lesson->id) {
                continue;
            }
            if ($lesson->group_no > 0) {
                $groupKey = "{$lesson->program_id}|{$lesson->semester_no}|{$lesson->code}";
                if (isset($seenGroups[$groupKey])) {
                    continue;
                }
                $seenGroups[$groupKey] = true;
            }
            $lessons[] = $lesson;
        }

        return $lessons;
    }

    /**
     * Tamamlanmamış bir ders için rapor satırını hazırlar: grup harfleri, birleşik sınav dersleri ve öğretim elemanları.
     *
     * @param array<int, string[]> $lecturers ders kimliği => öğretim elemanı adları
     */
    private function incompleteLessonRow(Lesson $lesson, array $lecturers, string $semester, string $academicYear): array
    {
        // IsScheduleComplete tarafından hesaplanan yerleşen kapasite ve kalan mevcut
        $placed = (int) $lesson->placed_size;
        $remaining = (int) $lesson->remaining_size;
        $linkedIds = $lesson->getExamAllLinkedLessonIds($semester, $academicYear);

        // Grup üyeleri (kendisi dahil) ve sınav birleştirmesiyle bağlı diğer dersler
        $memberIds = array_map(fn($member) => (int) $member->id, $lesson->getExamGroupLessons());
        $combinedIds = array_values(array_diff($linkedIds, $memberIds));

        $related = $this->lessonsById(array_merge($memberIds, $combinedIds));
        $groups = [];
        $names = [];
        foreach ($memberIds as $memberId) {
            $groupNo = $related[$memberId]['group_no'] ?? 0;
            if ($groupNo > 0 && $groupNo <= 26) {
                $groups[] = chr(64 + $groupNo);
            }
            array_push($names, ...($lecturers[$memberId] ?? []));
        }
        sort($groups);

        return [
            'lesson_id' => (int) $lesson->id,
            'semester_no' => (int) $lesson->semester_no,
            'class' => ClassOverlap::classOf(['semester_no' => $lesson->semester_no]),
            'code' => (string) $lesson->code,
            'name' => (string) $lesson->name,
            'groups' => $groups,
            'combined' => array_map(fn($id) => trim(($related[$id]['code'] ?? '') . ' ' . ($related[$id]['name'] ?? '')), $combinedIds),
            'lecturers' => array_values(array_unique(array_filter($names))),
            'size' => $placed + $remaining,
            'placed' => $placed,
            'remaining' => $remaining,
            'status' => $placed > 0 ? 'partial' : 'unplaced',
        ];
    }

    /**
     * @param int[] $ids
     * @return array<int, array{code:string, name:string, group_no:int, size:int}>
     */
    private function lessonsById(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT id, code, name, group_no, size FROM lessons WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);

        $lessons = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lessons[(int) $row['id']] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'group_no' => (int) $row['group_no'],
                'size' => (int) $row['size'],
            ];
        }

        return $lessons;
    }

    /**
     * Programların sınav programı kayıtlarını (single/group) ders kimliğiyle birlikte getirir.
     *
     * @param int[]|null $programIds
     * @return array<int, array{program_id:int, semester_no:int, day_index:int, week_index:int, start:string, end:string, lesson_id:int}>
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
                'start' => $row['start_time'],
                'end' => $row['end_time'],
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

    private function describe(array $item, array $lessons): array
    {
        $lesson = $lessons[$item['lesson_id']] ?? null;

        return [
            'class' => ClassOverlap::classOf($item),
            'lesson_id' => $item['lesson_id'],
            'code' => $lesson['code'] ?? '?',
            'name' => $lesson['name'] ?? '?',
            'group_no' => $lesson['group_no'] ?? 0,
            'size' => $lesson['size'] ?? 0,
            'start' => $item['start'],
            'end' => $item['end'],
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
