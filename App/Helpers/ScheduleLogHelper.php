<?php

namespace App\Helpers;

use App\Core\Log;
use App\DTOs\ScheduleItemDTO;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Schedule\SchedulePublishService;

class ScheduleLogHelper
{
    private const DAYS = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

    /**
     * ScheduleItemDTO için insan tarafından okunabilir işlem detay metni üretir.
     *
     * @param string $actionText Eylem açıklaması (örn: 'eklendi', 'taşındı', 'silindi', 'güncellendi')
     * @param ScheduleItemDTO $dto Güncel öğe DTO'su
     * @param ScheduleItemDTO|null $oldDto Eski öğe DTO'su (taşıma işlemlerinde)
     * @param bool $isExam Sınav programı olup olmadığı
     * @return string
     */
    public static function getChangeDetail(
        string $actionText,
        ScheduleItemDTO $dto,
        ?ScheduleItemDTO $oldDto = null,
        bool $isExam = false
    ): string {
        $examDate = $dto->detail['date'] ?? ($dto->detail['exam_date'] ?? null);
        $dayName = self::DAYS[$dto->dayIndex] ?? '';
        if (!empty($examDate)) {
            $dayName = $examDate . ' ' . $dayName;
        }
        $timeStr = $dto->startTime . ' - ' . $dto->endTime;

        $lessonNames = self::extractLessonNames($dto);
        $lessonTitle = !empty($lessonNames) ? implode(', ', $lessonNames) : ($isExam ? 'Sınav' : 'Ders');

        $lecturerNames = self::extractLecturerNames($dto, $isExam);
        $lecturerInfo = !empty($lecturerNames)
            ? ' (' . ($isExam ? 'Gözetmen: ' : 'Hoca: ') . implode(', ', $lecturerNames) . ')'
            : '';

        $typeSuffix = $isExam ? ' sınavı' : ' dersi';

        if ($oldDto) {
            $oldExamDate = $oldDto->detail['date'] ?? ($oldDto->detail['exam_date'] ?? null);
            $oldDayName = self::DAYS[$oldDto->dayIndex] ?? '';
            if (!empty($oldExamDate)) {
                $oldDayName = $oldExamDate . ' ' . $oldDayName;
            }
            $oldTimeStr = $oldDto->startTime . ' - ' . $oldDto->endTime;

            return sprintf(
                '"%s"%s%s %s %s saatinden %s %s saatine %s.',
                $lessonTitle,
                $typeSuffix,
                $lecturerInfo,
                $oldDayName,
                $oldTimeStr,
                $dayName,
                $timeStr,
                $actionText
            );
        }

        return sprintf(
            '"%s"%s%s %s %s saatine %s.',
            $lessonTitle,
            $typeSuffix,
            $lecturerInfo,
            $dayName,
            $timeStr,
            $actionText
        );
    }

    /**
     * ScheduleItemDTO içerisinden hoca / gözetmen ID'lerini ayıklar.
     *
     * @param ScheduleItemDTO $dto
     * @return array<int>
     */
    public static function extractLecturerIds(ScheduleItemDTO $dto): array
    {
        $lecturerIds = [];

        // Dersler için lecturer_id kontrolü
        if (!empty($dto->data) && is_array($dto->data)) {
            foreach ($dto->data as $entry) {
                if (is_array($entry) && !empty($entry['lecturer_id'])) {
                    $lecturerIds[] = (int)$entry['lecturer_id'];
                } elseif (isset($dto->data['lecturer_id'])) {
                    $lecturerIds[] = (int)$dto->data['lecturer_id'];
                }
            }
        }

        // Sınavlar için observer_id kontrolü
        $assignments = $dto->detail['assignments'] ?? [];
        if (!empty($assignments) && is_array($assignments)) {
            foreach ($assignments as $assignment) {
                if (!empty($assignment['observer_id'])) {
                    $lecturerIds[] = (int)$assignment['observer_id'];
                }
            }
        }

        return array_values(array_unique(array_filter($lecturerIds)));
    }

    /**
     * Schedule değişikliğini INFO seviyesinde loglar ve yayınlanmış programlar için ScheduleChangeQueue'ya kaydeder.
     *
     * @param string $actionType 'save', 'move', 'delete' vb.
     * @param string $actionText 'eklendi', 'taşındı', 'silindi' vb.
     * @param ScheduleItemDTO $dto
     * @param ScheduleItemDTO|null $oldDto
     * @param bool $isExam
     * @return void
     */
    public static function logAndRecordChange(
        string $actionType,
        string $actionText,
        ScheduleItemDTO $dto,
        ?ScheduleItemDTO $oldDto = null,
        bool $isExam = false
    ): void {
        $detail = self::getChangeDetail($actionText, $dto, $oldDto, $isExam);
        $lecturerIds = self::extractLecturerIds($dto);

        // 1. INFO Seviyesinde Monolog & DB Logs kaydı
        Log::logger()->info($detail, Log::context(null, [
            'schedule_id' => $dto->scheduleId,
            'action_type' => $actionType,
            'is_exam' => $isExam,
            'lecturer_ids' => $lecturerIds,
        ]));

        // 2. Yayınlanmış programlar için bildirim kuyruğu (ScheduleChangeQueue)
        $publishService = new SchedulePublishService();
        if (empty($lecturerIds)) {
            $publishService->recordChange($dto->scheduleId, $actionType, $detail, null);
        } else {
            foreach ($lecturerIds as $lecturerId) {
                $publishService->recordChange($dto->scheduleId, $actionType, $detail, $lecturerId);
            }
        }
    }

    /**
     * DTO'dan ders isimlerini ayıklar.
     *
     * @param ScheduleItemDTO $dto
     * @return array<string>
     */
    private static function extractLessonNames(ScheduleItemDTO $dto): array
    {
        $lessonIds = [];

        if (!empty($dto->data) && is_array($dto->data)) {
            foreach ($dto->data as $entry) {
                if (is_array($entry) && !empty($entry['lesson_id'])) {
                    $lessonIds[] = (int)$entry['lesson_id'];
                }
            }
            if (isset($dto->data['lesson_id'])) {
                $lessonIds[] = (int)$dto->data['lesson_id'];
            }
        }

        $lessonIds = array_unique(array_filter($lessonIds));
        $names = [];

        foreach ($lessonIds as $lessonId) {
            $lesson = (new Lesson())->find($lessonId);
            if ($lesson) {
                $names[] = $lesson->getFullName(true, true, true, true);
            }
        }

        return $names;
    }

    /**
     * DTO'dan hoca/gözetmen isimlerini ayıklar.
     *
     * @param ScheduleItemDTO $dto
     * @param bool $isExam
     * @return array<string>
     */
    private static function extractLecturerNames(ScheduleItemDTO $dto, bool $isExam): array
    {
        $lecturerIds = self::extractLecturerIds($dto);
        $names = [];

        foreach ($lecturerIds as $lecturerId) {
            $user = (new User())->find($lecturerId);
            if ($user) {
                $names[] = $user->getFullName();
            }
        }

        return $names;
    }
}
