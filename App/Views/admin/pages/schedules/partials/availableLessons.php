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
    data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin" data-bs-placement="left" data-bs-trigger="none">
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

        $isChild = !is_null($lesson->parent_lesson_id);

        // 1. Ders Türü Belirleme
        $typeClass = "lesson-type-normal"; // Varsayılan
        if ($isChild) {
            $typeClass = "lesson-type-child";
        } elseif ($lesson->classroom_type == 2) {
            $typeClass = "lesson-type-lab";
        } elseif ($lesson->classroom_type == 3) {
            $typeClass = "lesson-type-uzem";
        }

        // 2. Grup Belirleme (Opsiyonel Ek Sınıf)
        $groupClass = "";

        // Grup Kontrolü (Koddan Tespit: ".1", ".2" vb.)
        if (preg_match('/\.(\d+)$/', $lesson->code, $matches)) {
            $groupNum = (int) $matches[1];
            $groupMap = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];

            if (isset($groupMap[$groupNum])) {
                $groupClass = "lesson-group-" . $groupMap[$groupNum];
            } else {
                $groupClass = "lesson-group-a";
            }
        }

        // Nihai Sınıf Listesi
        $finalClass = trim("$typeClass $groupClass");

        $parentLesson = $isChild ? (new Lesson())->find($lesson->parent_lesson_id) : null;
        $popover = $isChild ? 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . ($parentLesson->program?->name ?? "") . ') dersine bağlı olduğu için düzenlenemez." data-bs-trigger="hover"' : "";

        $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . ($lesson->program?->name ?? "") . ')' : $lesson->name;
        // Badge yerine sağ alta gelecek metin
        $infoText = $filters['type'] == 'lesson' ? $lesson->hours . ' Saat' : $lesson->size . ' Kişi';
        ?>
        <div class='frame col-md-4 p-1'>
            <div id="available-lesson-<?= $lesson->id ?>" draggable="<?= $draggable ?>"
                class="lesson-card w-100 <?= $finalClass ?>" data-semester-no="<?= $lesson->semester_no ?>"
                data-semester="<?= $lesson->semester ?>" data-academic-year="<?= $lesson->academic_year ?>"
                data-lesson-code="<?= $lesson->code ?>" data-lesson-id="<?= $lesson->id ?>"
                data-lecturer-id="<?= $lesson->lecturer_id ?>" <?= $popover ?> data-lesson-hours="<?= $lesson->hours ?>"
                data-size="<?= ($lesson->size ?? 0) ?>">

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