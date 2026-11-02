<?php

namespace App\Services\Schedule\ExamRules;

use App\DTOs\ScheduleItemDTO;
use App\Enums\OwnerType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Services\Schedule\ExamScheduleService;
use Exception;

/**
 * Esnek sınav kurallarının ortak kullandığı veriyi (eklenecek sınavlar, etkilenen programlar,
 * programlardaki mevcut sınavlar) bir kez hazırlar ve önbelleğe alır.
 */
class ExamRuleContext
{
    /** @var array<int, array{dto: ScheduleItemDTO, lesson: Lesson, schedule: Schedule, label: string, programs: array}>|null */
    private ?array $newExams = null;

    /** @var array<int, true>|null Taşıma/düzenlemede silinecek item ID'leri (kardeş kayıtlar dahil) */
    private ?array $excludedIds = null;

    /** @var array<string, array> */
    private array $existingCache = [];

    /**
     * @param ScheduleItemDTO[] $dtos Eklenecek item DTO'ları
     * @param ScheduleItemDTO[] $deletedDtos Aynı işlemde silinecek item DTO'ları
     */
    public function __construct(
        private readonly array $dtos,
        private readonly array $deletedDtos,
        private readonly ExamScheduleService $examService
    ) {
    }

    /**
     * Eklenecek her sınav için ders, hedef program ve etkilenen programları (program_id + semester_no) döner.
     *
     * @return array<int, array{dto: ScheduleItemDTO, lesson: Lesson, schedule: Schedule, label: string, programs: array}>
     * @throws Exception
     */
    public function newExams(): array
    {
        if ($this->newExams !== null) {
            return $this->newExams;
        }

        $this->newExams = [];
        foreach ($this->dtos as $dto) {
            $schedule = (new Schedule())->find($dto->scheduleId);
            $lessonId = $dto->data[0]['lesson_id'] ?? $dto->data['lesson_id'] ?? null;
            if (!$schedule || !$lessonId) {
                continue;
            }

            $options = $this->relationOptions($schedule);
            $lesson = (new Lesson())->where(['id' => $lessonId])
                ->with(['examChildLessons' => $options, 'examParentLesson' => $options])
                ->first();
            if (!$lesson) {
                continue;
            }

            $this->newExams[] = [
                'dto' => $dto,
                'lesson' => $lesson,
                'schedule' => $schedule,
                'label' => $this->lessonLabel($lesson),
                'programs' => $this->resolveExamPrograms($lesson, $options),
            ];
        }

        return $this->newExams;
    }

    /**
     * Bir programın (program_id) verilen gün/haftadaki mevcut sınavlarını döner.
     * Silinecek sınavlar ve dummy (tercih/müsait değil) kayıtlar dahil edilmez.
     *
     * @param int|null $semesterNo Verilirse yalnızca o yarıyılın programına bakılır; null ise programın tüm yarıyılları
     * @return array<int, array{semester_no: int, start: string, end: string, label: string, root: int}>
     * @throws Exception
     */
    public function existingExams(int $programId, Schedule $targetSchedule, int $dayIndex, int $weekIndex, ?int $semesterNo = null): array
    {
        $key = "{$programId}|{$semesterNo}|{$targetSchedule->id}|{$dayIndex}|{$weekIndex}";
        if (isset($this->existingCache[$key])) {
            return $this->existingCache[$key];
        }

        $filters = [
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $programId,
            'semester' => $targetSchedule->semester,
            'academic_year' => $targetSchedule->academic_year,
            'type' => $targetSchedule->type,
        ];
        if ($semesterNo !== null) {
            $filters['semester_no'] = $semesterNo;
        }

        $result = [];
        foreach ((new Schedule())->get()->where($filters)->all() as $schedule) {
            $dayItems = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'day_index' => $dayIndex,
                'week_index' => $weekIndex,
            ])->all();

            foreach ($dayItems as $item) {
                if ($this->isExcluded($item) || !in_array($item->status, ['single', 'group'])) {
                    continue;
                }
                $lessonId = (int) ($item->data[0]['lesson_id'] ?? 0);
                $lesson = $lessonId
                    ? (new Lesson())->where(['id' => $lessonId])->with(['examParentLesson' => $this->relationOptions($targetSchedule)])->first()
                    : null;

                $result[] = [
                    'semester_no' => (int) $schedule->semester_no,
                    'start' => $item->start_time,
                    'end' => $item->end_time,
                    'label' => $lesson ? $this->lessonLabel($lesson) : '',
                    'root' => $lesson ? $this->examRootId($lesson) : 0,
                ];
            }
        }

        return $this->existingCache[$key] = $result;
    }

    /**
     * Aynı birleşik sınava (lesson_combinations, type=exam) ait dersler için ortak kimlik:
     * alt dersin kimliği üst dersin kimliğidir. Lesson'ın examParentLesson ilişkisi yüklenmiş olmalıdır.
     */
    public function examRootId(Lesson $lesson): int
    {
        return (int) ($lesson->examParentLesson?->id ?: $lesson->id);
    }

    /**
     * Sınav birleştirme ilişkilerinin (lesson_combinations) hedef programın dönemine göre yüklenmesi için seçenekler.
     */
    private function relationOptions(Schedule $schedule): array
    {
        return ['semester' => $schedule->semester, 'academic_year' => $schedule->academic_year];
    }

    public function lessonLabel(Lesson $lesson): string
    {
        return $lesson->getFullName(true, false, true, true) ?: (string) $lesson->code;
    }

    /**
     * Bir sınavdan etkilenen benzersiz program (program_id + semester_no) listesini döner.
     * ExamScheduleService::saveExamScheduleItems'taki program owner belirleme mantığıyla aynıdır
     * (exam parent, gruplu dersler ve sınav birleştirmeleri).
     *
     * @return array<int, array{program_id: int, semester_no: int}>
     */
    private function resolveExamPrograms(Lesson $lesson, array $relationOptions): array
    {
        $mainLesson = !empty($lesson->examParentLesson) ? $lesson->examParentLesson : $lesson;

        $lessons = [$mainLesson];
        if ($mainLesson->group_no > 0) {
            $siblings = (new Lesson())->get()->where([
                'code' => $mainLesson->code,
                'program_id' => $mainLesson->program_id,
                'semester_no' => $mainLesson->semester_no,
                'group_no' => ['>' => 0],
                'id' => ['!=' => $mainLesson->id]
            ])->with(['examChildLessons' => $relationOptions])->all();
            $lessons = array_merge($lessons, $siblings);
        }

        $programs = [];
        foreach ($lessons as $l) {
            $programs["{$l->program_id}|{$l->semester_no}"] = ['program_id' => (int) $l->program_id, 'semester_no' => (int) $l->semester_no];
            foreach ($l->examChildLessons ?? [] as $child) {
                $programs["{$child->program_id}|{$child->semester_no}"] = ['program_id' => (int) $child->program_id, 'semester_no' => (int) $child->semester_no];
            }
        }

        return array_values($programs);
    }

    private function isExcluded(ScheduleItem $item): bool
    {
        if ($this->excludedIds === null) {
            $this->excludedIds = [];
            foreach ($this->deletedDtos as $deletedDto) {
                $id = (int) ($deletedDto->id ?? 0);
                $deleted = $id ? (new ScheduleItem())->find($id) : null;
                if (!$deleted) {
                    continue;
                }
                foreach ($this->examService->findExamSiblingItems($deleted) as $sibling) {
                    $this->excludedIds[(int) $sibling->id] = true;
                }
            }
        }

        return isset($this->excludedIds[(int) $item->id]);
    }
}
