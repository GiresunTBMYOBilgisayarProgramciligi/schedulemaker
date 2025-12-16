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
<div class="row available-schedule-items drop-zone small"
    data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin" data-bs-placement="left" data-bs-trigger="none">
    <?php foreach ($availableLessons as $lesson): ?>
        <?php
        /**
         * @var Lesson $lesson
         * @var Lesson $parentLesson
         */
        $draggable = "true";
        if (!is_null($lesson->parent_lesson_id) or $schedule->academic_year != getSettingValue('academic_year') or $schedule->semester != getSettingValue('semester')) {
            $draggable = "false";
        }

        $isChild = !is_null($lesson->parent_lesson_id);

        $parentLesson = $isChild ? (new Lesson())->find($lesson->parent_lesson_id) : null;
        $popover = $isChild ? 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . ($parentLesson->program?->name ?? "") . ') dersine bağlı olduğu için düzenlenemez." data-bs-trigger="hover"' : "";

        $lessonName = in_array($schedule->owner_type, ['user', 'classroom']) ? $lesson->name . ' (' . ($lesson->program?->name ?? "") . ')' : $lesson->name;
        // Badge yerine sağ alta gelecek metin
        $infoText = $schedule->type == 'lesson' ? $lesson->hours . ' Saat' : $lesson->size . ' Kişi';
        ?>
        <div class='frame col-md-4 p-1'>
            <div id="available-lesson-<?= $lesson->id ?>" draggable="<?= $draggable ?>"
                class="lesson-card w-100 <?= $lesson->getScheduleCSSClass() ?>" 
                data-lesson-id="<?= $lesson->id ?>"
                data-lesson-hours="<?= $lesson->hours ?>"
                data-size="<?= ($lesson->size ?? 0) ?>"
                data-group-no="<?= $lesson->group_no ?>"
                data-lesson-code="<?= $lesson->code ?>"
                data-lecturer-id="<?= $lesson->lecturer_id ?>"
                <?= $popover ?>
                >

                <span class="lesson-name" title="<?= $lesson->code ?>">
                    <a class='text-decoration-none' target='_blank' style="color: inherit;"
                        href='/admin/lesson/<?= $lesson->id ?>'>
                        <?= $lessonName ?>
                    </a>
                </span>

                <div class="lesson-meta">
                    <span class="lesson-lecturer">
                        <a class="text-decoration-none" target='_blank' style="color: inherit;"
                            href="/admin/profile/<?= $lesson->lecturer_id ?>">
                            <?= $lesson->lecturer?->getFullName() ?>
                        </a>
                    </span>
                    <span class="lesson-classroom">
                        <?= $infoText ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div><!--end::available-schedule-items-->