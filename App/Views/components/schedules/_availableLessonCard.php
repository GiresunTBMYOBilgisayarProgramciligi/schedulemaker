<?php
use App\Core\View;
use App\Helpers\ScheduleViewHelper;

/**
 * Available lessons panelindeki tek bir ders kartı component'i.
 *
 * Hem gerçek dersler hem dummy kartlar (preferred/unavailable) için kullanılır.
 * Frame wrapper (col-md-3) dahildir.
 *
 * Beklenen değişkenler:
 * @var \App\Models\Lesson|object $lesson  Lesson modeli veya dummy obje
 * @var \App\Models\Schedule $schedule     Üst schedule nesnesi
 * @var bool $isDummy                      Dummy kart mı
 */

$attrString = ScheduleViewHelper::renderAttributes(
    ScheduleViewHelper::buildAvailableLessonAttributes($lesson, $schedule, $isDummy)
);

$lessonName = ScheduleViewHelper::getAvailableLessonName($lesson, $schedule, $isDummy);
$infoText = ScheduleViewHelper::getAvailableLessonInfoText($lesson, $schedule, $isDummy);

// Popover: Birleştirilmiş (child) dersler için
$popoverAttr = '';
if (!$isDummy) {
    $isExam = in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam']);
    if ($isExam && !is_null($lesson->exam_parent_lesson_id)) {
        $parent = $lesson->examParentLesson ?? null;
        if ($parent) {
            $popoverTitle = 'Sınav Birleştirmesi';
            $popoverContent = 'Bu dersin sınavı, ' . $parent->getFullName(addCode: true, addProgram: true) . ' dersine bağlıdır.';
            $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
        }
    } elseif (!$isExam && !is_null($lesson->parent_lesson_id)) {
        $parent = $lesson->parentLesson ?? null;
        if ($parent) {
            $popoverTitle = 'Birleştirilmiş Ders';
            $popoverContent = 'Bu ders ' . $parent->getFullName(addCode: true, addProgram: true) . ' dersine bağlı olduğu için düzenlenemez.';
            $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
        }
    }
}
?>
<div class='frame col-md-3 p-1'>
    <div <?= $attrString ?> <?= $popoverAttr ?>>
        <span class="lesson-name" title="<?= htmlspecialchars($lessonName) ?>">
            <?= htmlspecialchars($lessonName) ?>
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

        <?php if (!$isDummy): ?>
            <?= View::renderComponent('schedules/_childLessons', [
                'lesson' => $lesson,
                'type' => in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson'
            ]) ?>
        <?php endif; ?>
    </div>
</div>
