<?php

namespace App\Helpers;

use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use Exception;
use App\Core\View;
use App\DTOs\ScheduleFilterDTO;
use App\Services\Schedule\AvailabilityService;
use App\Services\Schedule\ScheduleService;
use App\Models\User;
use App\Models\Program;
use App\Models\Classroom;
use function App\Helpers\getSettingValue;

/**
 * ScheduleViewHelper
 *
 * Schedule tablo görünümlerinde kullanılan ortak yardımcı fonksiyonlar.
 * lesson-card ve empty-slot elemanları için data attribute'larını oluşturur.
 */
class ScheduleViewHelper
{
    /**
     * Lesson card için data attribute dizisi oluşturur.
     *
     * @param ScheduleItem $scheduleItem İlgili schedule item
     * @param object $slotData Slot verisi (lesson, lecturer, classroom)
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $draggable Sürüklenebilir mi
     * @param string $type Tablo tipi: 'lesson' veya 'exam'
     * @return array Key-value şeklinde HTML attribute dizisi
     */
    public static function buildLessonCardAttributes(
        ScheduleItem $scheduleItem,
        object       $slotData,
        Schedule     $schedule,
        bool         $draggable,
        string       $type = 'lesson'
    ): array
    {
        $isExam = ($type === 'exam');
        $cssClass = "lesson-card " . $slotData->lesson->getScheduleCSSClass($isExam);
        if ($isExam) {
            $cssClass = "lesson-card h-100 m-0 " . $slotData->lesson->getScheduleCSSClass(true);
        }

        $attrs = [
            'draggable' => $draggable ? 'true' : 'false',
            'class' => $cssClass,
            'data-schedule-item-id' => $scheduleItem->id,
            'data-group-no' => $slotData->lesson->group_no,
            'data-lesson-id' => $slotData->lesson->id,
            'data-lesson-code' => $slotData->lesson->code,
            'data-lesson-name' => $slotData->lesson->getFullName(addCode: true),
            'data-size' => $slotData->lesson->size,
            'data-lecturer-id' => $slotData->lecturer?->id,
            'data-lecturer-name' => $slotData->lecturer?->getFullName(),
            'data-classroom-id' => $slotData->classroom?->id,
            'data-classroom-name' => $slotData->classroom?->name,
            'data-classroom-size' => $slotData->classroom?->class_size,
            'data-classroom-exam-size' => $slotData->classroom?->exam_size,
            'data-status' => $scheduleItem->status,
        ];

        // Exam tablosunda detail attribute'u eklenir
        if ($type === 'exam') {
            $attrs['data-detail'] = json_encode($scheduleItem->detail);
        }

        // Program bilgisi (program dışındaki schedule türlerinde)
        if ($schedule->owner_type !== OwnerType::PROGRAM->value) {
            $attrs['data-program-id'] = $slotData->lesson->program_id;
            $attrs['data-program-name'] = $slotData->lesson->program?->name;

            // Child lesson program bilgileri
            if ($type === 'lesson' && count($slotData->lesson->childLessons) > 0) {
                foreach ($slotData->lesson->childLessons as $childLesson) {
                    $attrs['data-child-lessons-' . $childLesson->id . '-program-id'] = $childLesson->program_id;
                    $attrs['data-child-lessons-' . $childLesson->id . '-program-name'] = $childLesson->program?->name;
                }
            } elseif ($type === 'exam' && count($slotData->lesson->examChildLessons ?? []) > 0) {
                foreach ($slotData->lesson->examChildLessons as $examChild) {
                    $attrs['data-child-lessons-' . $examChild->id . '-program-id'] = $examChild->program_id;
                    $attrs['data-child-lessons-' . $examChild->id . '-program-name'] = $examChild->program?->name;
                }
            }
        }

        return $attrs;
    }

    /**
     * Attribute dizisini HTML string'e dönüştürür.
     *
     * @param array $attrs Key-value attribute dizisi
     * @return string HTML attribute string
     */
    public static function renderAttributes(array $attrs): string
    {
        $result = "";
        foreach ($attrs as $key => $val) {
            $result .= " $key=\"" . htmlspecialchars((string)($val ?? "")) . "\"";
        }
        return $result;
    }

    /**
     * Available lessons panelindeki ders kartı için data attribute dizisi oluşturur.
     *
     * Tablo içindeki buildLessonCardAttributes'ten farklı olarak ScheduleItem yerine
     * doğrudan Lesson modeli veya dummy obje ile çalışır.
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı (preferred/unavailable)
     * @return array Key-value şeklinde HTML attribute dizisi
     */
    public static function buildAvailableLessonAttributes(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): array
    {
        // Draggable belirleme
        $draggable = 'true';
        if (!$isDummy) {
            // Sınav programında exam_parent_lesson_id, ders programında parent_lesson_id
            $isExam = ExamType::isExamType($schedule->type);
            $isChild = $isExam
                ? !is_null($lesson->exam_parent_lesson_id)
                : !is_null($lesson->parent_lesson_id);

            if ($isChild
                || $schedule->academic_year != getSettingValue('academic_year')
                || $schedule->semester != getSettingValue('semester')
            ) {
                $draggable = 'false';
            }
        }

        // CSS sınıfı belirleme
        if ($isDummy) {
            $status = $lesson->status ?? '';
            $cssClass = "dummy w-100 slot-" . $status;
        } else {
            /** @var Lesson $lesson */
            $isExam = ExamType::isExamType($schedule->type);
            $cssClass = "lesson-card w-100 " . $lesson->getScheduleCSSClass($isExam);
        }

        $attrs = [
            'id' => 'available-lesson-' . $lesson->id,
            'draggable' => $draggable,
            'class' => $cssClass,
            'data-lesson-id' => $lesson->id,
            'data-lesson-hours' => $lesson->hours ?? 1,
            'data-group-no' => $isDummy ? 0 : $lesson->group_no,
            'data-lesson-code' => $lesson->code,
            'data-lecturer-id' => $lesson->lecturer_id ?? null,
            'data-status' => $isDummy ? ($lesson->status ?? '') : '',
            'data-program-id' => $isDummy ? null : $lesson->program_id,
            'data-size' => $isDummy ? null : ($lesson->size ?? 0),
            // Sağ-tık menü için isimler
            'data-program-name' => $isDummy ? null : ($lesson->program->name ?? null),
            'data-lecturer-name' => $isDummy ? null : ($lesson->lecturer?->getFullName()),
            'data-lesson-name' => $isDummy ? null : $lesson->getFullName(addCode: true),
        ];

        if ($isDummy) {
            $attrs['data-is-dummy'] = 'true';
        }

        return $attrs;
    }

    /**
     * Available lessons panelindeki ders adını formatlar.
     *
     * Schedule tipi ve owner type'a göre farklı formatlama uygular.
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı
     * @return string Formatlanmış ders adı
     */
    public static function getAvailableLessonName(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): string
    {
        if ($isDummy) {
            return $lesson->name ?? '';
        }

        /** @var Lesson $lesson */
        if ($schedule->type === 'lesson') {
            // Ders programında grup bilgisi eklenir
            if (in_array($schedule->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value])) {
                return $lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: true);
            }
            return $lesson->getFullName(addGroup: true);
        }

        // Sınav programında sadece ders adı
        if (in_array($schedule->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value])) {
            return $lesson->getFullName(addProgram: true, addClassNumber: true);
        }
        return $lesson->getFullName();
    }

    /**
     * Available lessons panelindeki bilgi metnini üretir.
     *
     * Ders programı → "X Saat", Sınav programı → "X Kişi"
     *
     * @param object $lesson Lesson modeli veya dummy obje
     * @param Schedule $schedule Üst schedule nesnesi
     * @param bool $isDummy Dummy kart mı
     * @return string Bilgi metni
     */
    public static function getAvailableLessonInfoText(
        object   $lesson,
        Schedule $schedule,
        bool     $isDummy = false
    ): string
    {
        if ($isDummy) {
            return '';
        }

        return $schedule->type === 'lesson'
            ? ($lesson->hours ?? 0) . ' Saat'
            : ($lesson->size ?? 0) . ' Kişi';
    }

    /**
     * Ders kartının sürüklenebilir olup olmadığını belirler.
     *
     * @param object $slotData Slot verisi
     * @param Schedule $schedule Üst schedule
     * @param bool $onlyTable Salt okunur tablo mu
     * @param bool $preferenceMode Tercih modu mu
     * @return bool
     */
    public static function isDraggable(
        object   $slotData,
        Schedule $schedule,
        bool     $onlyTable = false,
        bool     $preferenceMode = false
    ): bool
    {
        // Sınav programında exam_parent_lesson_id, ders programında parent_lesson_id
        $isExam = ExamType::isExamType($schedule->type);
        $isChild = $isExam
            ? !is_null($slotData->lesson->exam_parent_lesson_id)
            : !is_null($slotData->lesson->parent_lesson_id);
        if ($isChild) {
            return false;
        }
        if ($schedule->academic_year != getSettingValue('academic_year')) {
            return false;
        }
        if ($schedule->semester != getSettingValue('semester')) {
            return false;
        }
        if ($onlyTable || $preferenceMode) {
            return false;
        }
        return true;
    }

    /**
     * Tablo oluşturulurken kullanılacak boş hafta listesi. her saat için bir tane kullanılır.
     * @param int|null $maxDayIndex haftanın hangi gününe kadar program oluşturulacağını belirler
     * @return array
     * @throws Exception
     */
    public static function generateEmptyWeek(?int $maxDayIndex = null): array
    {
        if ($maxDayIndex === null)
            throw new Exception("maxDayIndex belirtilmelidir");
        $emptyWeek = [];
        
        foreach (range(0, $maxDayIndex) as $index) {
            $emptyWeek["day{$index}"] = null;
        }
        return $emptyWeek;
    }

    /**
     * Ders programı tablosunun verilerini oluşturur
     * Sadece tek bir tablo için veri oluşturur. Farklı dönem numaraları birleştirilecekse bu işlem sonradan yapılmalı.
     * @throws Exception
     * @return array
     */
    public static function prepareScheduleRows(Schedule $schedule, $maxDayIndex = null): array
    {
        if ($maxDayIndex === null) {
            $scheduleTypeStr = ExamType::isExamType($schedule->type) ? 'exam' : 'lesson';
            $maxDayIndex = getSettingValue('maxDayIndex', $scheduleTypeStr, 4);
        }

        $scheduleRows = [];
        $weekCount = ($schedule->type === ExamType::FINAL->value) ? 2 : 1;

        for ($w = 0; $w < $weekCount; $w++) {
            $scheduleRows[$w] = [];
            if (ExamType::isExamType($schedule->type)) {
                $duration = getSettingValue('duration', 'exam', 30);
                $break = getSettingValue('break', 'exam', 0);
                $start = new \DateTime('08:00');
                $end = new \DateTime('17:00');
                while ($start < $end) {
                    $slotStartTime = clone $start;
                    $slotEndTime = (clone $start)->modify("+$duration minutes");
                    $scheduleRows[$w][] = [
                        'slotStartTime' => $slotStartTime,
                        'slotEndTime' => $slotEndTime,
                        'days' => self::generateEmptyWeek($maxDayIndex)
                    ];

                    $start = (clone $slotEndTime)->modify("+$break minutes");
                }
            } else {
                $duration = getSettingValue('duration', 'lesson', 50);
                $break = getSettingValue('break', 'lesson', 10);
                $start = new \DateTime('08:00');
                $end = new \DateTime('17:00');
                while ($start < $end) {
                    $slotStartTime = clone $start;
                    $slotEndTime = (clone $start)->modify("+$duration minutes");
                    $scheduleRows[$w][] = [
                        'slotStartTime' => $slotStartTime,
                        'slotEndTime' => $slotEndTime,
                        'days' => self::generateEmptyWeek($maxDayIndex)
                    ];
                    $start = (clone $slotEndTime)->modify("+$break minutes");
                }
            }
        }

        foreach ($schedule->items as $scheduleItem) {
            $itemStart = \DateTime::createFromFormat('H:i:s', $scheduleItem->start_time) ?: \DateTime::createFromFormat('H:i', $scheduleItem->start_time);
            $itemEnd = \DateTime::createFromFormat('H:i:s', $scheduleItem->end_time) ?: \DateTime::createFromFormat('H:i', $scheduleItem->end_time);

            if (!$itemStart || !$itemEnd)
                continue;

            foreach ($scheduleRows[$scheduleItem->week_index] as &$row) {
                $slotStart = $row['slotStartTime'];

                if ($slotStart->format('H:i') >= $itemStart->format('H:i') && $slotStart->format('H:i') < $itemEnd->format('H:i')) {
                    $dayKey = 'day' . $scheduleItem->day_index;

                    if (array_key_exists($dayKey, $row['days'])) {
                        if ($row['days'][$dayKey] === null) {
                            $row['days'][$dayKey] = $scheduleItem;
                        } else {
                            $existing = $row['days'][$dayKey];

                            if (is_array($existing)) {
                                continue;
                            }

                            if (in_array($scheduleItem->status, ['preferred', 'unavailable'])) {
                                continue;
                            } elseif (in_array($existing->status, ['preferred', 'unavailable'])) {
                                $row['days'][$dayKey] = $scheduleItem;
                            } else {
                                if (!is_array($row['days'][$dayKey])) {
                                    $row['days'][$dayKey] = [$row['days'][$dayKey]];
                                }
                                $row['days'][$dayKey][] = $scheduleItem;
                            }
                        }
                    }
                }
            }
        }

        return $scheduleRows;
    }

    /**
     * Ders programı düzenleme sayfasında, ders profil, bölüm ve program sayfasındaki Ders program kartlarının html çıktısını oluşturur
     * @throws Exception
     */
    public static function prepareScheduleCard(ScheduleFilterDTO $dto, bool $only_table = false, bool $preference_mode = false, bool $no_card = false): string
    {
        if (in_array($dto->owner_type, [OwnerType::USER->value, OwnerType::CLASSROOM->value, OwnerType::LESSON->value])) {
            $data = $dto->toArray();
            $data['semester_no'] = null;
            $dto = ScheduleFilterDTO::fromArray($data);
        }

        $scheduleService = new ScheduleService();
        $schedule = $scheduleService->getOrCreateSchedule($dto);
        $availableLessons = ($only_table) ? [] : (new AvailabilityService())->availableLessons($schedule, $preference_mode);
        $scheduleRows = self::prepareScheduleRows($schedule);

        $availableLessonsHTML = View::renderPartial('admin', 'schedules', 'availableLessons', [
            'availableLessons' => $availableLessons,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode,
            'owner_type' => $dto->owner_type ?? null
        ]);

        $createTableHeaders = function (int $weekIndex = 0) use ($dto): array {
            $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
            $headers = [];
            $isExam = ExamType::isExamType($dto->type);
            $type = $isExam ? 'exam' : 'lesson';

            $startDate = null;
            if ($isExam) {
                $examTypeEnum = ExamType::tryFrom($dto->type);
                if ($examTypeEnum) {
                    $settingKey = $examTypeEnum->startDateSettingKey();
                    if ($settingKey) {
                        $startDateString = getSettingValue($settingKey, 'exam');
                        if ($startDateString) {
                            $startDate = new \DateTime($startDateString);
                        }
                    }
                }
            }

            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
            for ($i = 0; $i <= $maxDayIndex; $i++) {
                $headerTitle = $days[$i];
                if ($startDate) {
                    $currentDate = (clone $startDate)->modify("+" . ($weekIndex * 7 + $i) . " days");
                    $headerTitle .= '<br><small>' . $currentDate->format('d.m.Y') . '</small>';
                }
                $headers[] = '<th>' . $headerTitle . '</th>';
            }
            return $headers;
        };

        $allWeekHeaders = [];
        foreach ($scheduleRows as $weekIndex => $rows) {
            $allWeekHeaders[$weekIndex] = $createTableHeaders($weekIndex);
        }

        $isExam = ExamType::isExamType($schedule->type);
        $partialName = $isExam ? 'examScheduleTable' : 'lessonScheduleTable';

        $scheduleTableHTML = View::renderPartial('admin', 'schedules', $partialName, [
            'weekRows' => $scheduleRows,
            'weekHeaders' => $allWeekHeaders,
            'schedule' => $schedule,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode
        ]);

        $ownerName = match ($dto->owner_type) {
            OwnerType::USER->value => (new User())->find($dto->owner_id)->getFullName(),
            OwnerType::PROGRAM->value => (new Program())->find($dto->owner_id)->name,
            OwnerType::CLASSROOM->value => (new Classroom())->find($dto->owner_id)->name,
            OwnerType::LESSON->value => (new Lesson())->find($dto->owner_id)->getFullName(true),
            default => ""
        };

        //Semester No dizi ise dönemler birleştirilmiş demektir. Birleştirilmişse Başlık olarak Ders programı yazar
        $cardTitle = $dto->semester_no . " Yarıyıl Programı";
        $dataSemesterNo = 'data-semester-no="' . $dto->semester_no . '"';

        if (ExamType::isExamType($dto->type)) {
            $duration = getSettingValue('duration', 'exam', 30);
            $break = getSettingValue('break', 'exam', 0);
        } else {
            $duration = getSettingValue('duration', 'lesson', 50);
            $break = getSettingValue('break', 'lesson', 10);
        }

        return View::renderPartial('admin', 'schedules', 'scheduleCard', [
            'schedule' => $schedule,
            'availableLessonsHTML' => $availableLessonsHTML,
            'scheduleTableHTML' => $scheduleTableHTML,
            'ownerName' => $ownerName,
            'cardTitle' => $cardTitle,
            'dataSemesterNo' => $dataSemesterNo,
            'duration' => $duration,
            'break' => $break,
            'only_table' => $only_table,
            'preference_mode' => $preference_mode,
            'weekCount' => count($scheduleRows),
            'no_card' => $no_card
        ]);
    }
}
