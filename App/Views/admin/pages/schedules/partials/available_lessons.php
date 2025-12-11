<?php
use App\Models\Lesson;
use App\Models\User;
use App\Models\Classroom;
use function App\Helpers\getSettingValue;

/**
 * @var array $availableLessons
 * @var array $filters
 */

/*
 * Semester no dizi olarak gelmişse sınıflar birleştirilmiş demektir. Bu da Tekil sayfalarda kullanılıyor (Hoca,ders,derslik)
 */
$semester_no = is_array($filters["semester_no"]) ? "" : $filters["semester_no"];
?>
<div class="row available-schedule-items drop-zone small" data-semester-no="<?= $semester_no ?>"
    data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin" data-bs-trigger="data-bs-placement=" left"">
    <?php foreach ($availableLessons as $lesson): ?>
        <?php
        /**
         * @var Lesson $lesson
         * @var Lesson $parentLesson
         */
        $draggable = "true";
        if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
            $draggable = "false";
        }
        $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
        $badgeCSS = is_null($lesson->parent_lesson_id) ? "bg-info" : "bg-light text-dark";
        $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
        $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . ($parentLesson->program?->name ?? "") . ') dersine bağlı olduğu için düzenlenemez."';
        /**
         * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
         */
        $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . ($lesson->program?->name ?? "") . ')' : $lesson->name;
        $badgeText = $filters['type'] == 'lesson' ? $lesson->hours : $lesson->size;
        ?>
        <div class='frame col-md-4 p-0 ps-1 '>
            <div id="available-lesson-<?= $lesson->id ?>" draggable="<?= $draggable ?>"
                class="d-flex justify-content-between align-items-start mb-1 p-2 rounded <?= $text_bg ?>"
                data-semester-no="<?= $lesson->semester_no ?>" data-semester="<?= $lesson->semester ?>"
                data-academic-year="<?= $lesson->academic_year ?>" data-lesson-code="<?= $lesson->code ?>"
                data-lesson-id="<?= $lesson->id ?>" data-lecturer-id="<?= $lesson->lecturer_id ?>" <?= $popover ?>
                data-lesson-hours="<?= $lesson->hours ?>" data-size="<?= ($lesson->size ?? 0) ?>">
                <div class="ms-2 me-auto">
                    <div class="fw-bold lesson-title" data-bs-toggle="tooltip" data-bs-placement="left"
                        title=" <?= $lesson->code ?> ">
                        <a class='link-light link-underline-opacity-0' target='_blank'
                            href='/admin/lesson/<?= $lesson->id ?>'>
                            <i class="bi bi-book"></i>
                        </a>
                        <?= $lessonName ?>
                    </div>
                    <div class="text-nowrap lecturer-title" id="lecturer-<?= $lesson->lecturer_id ?>">
                        <a class="link-light link-underline-opacity-0" target='_blank'
                            href="/admin/profile/<?= $lesson->lecturer_id ?>">
                            <i class="bi bi-person-square"></i>
                        </a>
                        <?= $lesson->lecturer?->getFullName() ?>
                    </div>

                </div>
                <span class="badge <?= $badgeCSS ?> rounded-pill"><?= $badgeText ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div><!--end::available-schedule-items-->