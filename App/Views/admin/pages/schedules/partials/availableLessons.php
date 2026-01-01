<?php
use App\Models\Lesson;
use App\Models\Schedule;
use function App\Helpers\getSettingValue;
use App\Core\Log;
/**
 * @var array $availableLessons
 * @var Schedule $schedule
 */
?>
<div class="row available-schedule-items drop-zone small" data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin"
    data-bs-placement="left" data-bs-trigger="none">
    <?php foreach ($availableLessons as $lesson): ?>
        <?php
        /**
         * @var Lesson|object $lesson
         */
        $isDummy = isset($lesson->is_dummy) && $lesson->is_dummy;

        $draggable = "true";
        if (!$isDummy) {
            if (!is_null($lesson->parent_lesson_id) or $schedule->academic_year != getSettingValue('academic_year') or $schedule->semester != getSettingValue('semester')) {
                $draggable = "false";
            }
        }

        $popover = "";
        if (!$isDummy) {
            $isChild = !is_null($lesson->parent_lesson_id);
            $parentLesson = $isChild ? (new Lesson())->find($lesson->parent_lesson_id) : null;
            $popover = $isChild ? 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . ($parentLesson->program?->name ?? "") . ') dersine bağlı olduğu için düzenlenemez." data-bs-trigger="hover"' : "";
        }

        $status = "";
        if ($isDummy) {
            $status = isset($lesson->status) ? $lesson->status : "";
        } else {
            /** @var Lesson $lesson */
            $status = $lesson->getScheduleCSSClass();
        }
        $lessonName = $lesson->name;
        if (!$isDummy && in_array($schedule->owner_type, ['user', 'classroom'])) {
            $lessonName = $lesson->name . ' (' . ($lesson->program?->name ?? "") . ')';
        }

        // Badge yerine sağ alta gelecek metin
        $infoText = "";
        if ($isDummy) {
            $infoText = "";
        } else {
            $infoText = $schedule->type == 'lesson' ? $lesson->hours . ' Saat' : $lesson->size . ' Kişi';
        }

        $dataAttrs = [
            'draggable' => $draggable,
            'class' => ($isDummy ? "dummy" : "lesson-card") . " w-100 " . ($isDummy ? "slot-" . $status : $status),
            'data-lesson-id' => $isDummy ? $lesson->id : $lesson->id,
            'data-lesson-hours' => $lesson->hours ?? 1,
            'data-group-no' => $isDummy ? 0 : $lesson->group_no,
            'data-lesson-code' => $lesson->code,
            'data-lecturer-id' => $isDummy ? $lesson->lecturer_id : $lesson->lecturer_id,
            'data-status' => $isDummy ? $status : '', // dummy ise preferred/unavailable, değilse boş (JS group/single olarak belirleyecek)
            'data-program-id' => $lesson->program_id,
        ];

        if ($isDummy) {
            $dataAttrs['data-is-dummy'] = 'true';
        } else {
            $dataAttrs['data-size'] = ($lesson->size ?? 0);
        }

        $attrString = "";
        foreach ($dataAttrs as $key => $val) {
            $attrString .= " $key=\"$val\"";
        }
        ?>
        <div class='frame col-md-4 p-1'>
            <div id="available-lesson-<?= $lesson->id ?>" <?= $attrString ?>     <?= $popover ?>>

                <span class="lesson-name" title="<?= $lesson->code ?>">
                    <?php if ($isDummy): ?>
                        <?= $lessonName ?>
                    <?php else: ?>
                        <?= $lessonName ?>
                    <?php endif; ?>
                </span>

                <div class="lesson-meta">
                    <span class="lesson-lecturer">
                        <?php if ($isDummy): ?>
                            -
                        <?php else: ?>
                            <?= $lesson->lecturer?->getFullName() ?>
                        <?php endif; ?>
                    </span>
                    <span class="lesson-classroom">
                        <?= $infoText ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div><!--end::available-schedule-items-->