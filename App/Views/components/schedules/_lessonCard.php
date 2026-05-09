<?php
use App\Core\View;
use App\Helpers\ScheduleViewHelper;

/**
 * Ders kartı (lesson-card) partial'ı
 *
 * Hem ders hem sınav programı tablolarında ortak kullanılır.
 * Tipine ('exam' | 'lesson') göre farklı detaylar (gözetmenler vs.) render edebilir.
 *
 * Beklenen değişkenler:
 * @var \App\Models\ScheduleItem $scheduleItem
 * @var object $slotData
 * @var \App\Models\Schedule $schedule
 * @var bool $draggable
 * @var string $type  'exam' veya 'lesson'
 * @var bool|null $only_table
 * @var bool|null $preference_mode
 */

$attrString = ScheduleViewHelper::renderAttributes(
    ScheduleViewHelper::buildLessonCardAttributes(
        $scheduleItem,
        $slotData,
        $schedule,
        $draggable,
        $type
    )
);

$popoverAttr = "";
if ($type === 'lesson') {
    $isChild = !is_null($slotData->lesson->parent_lesson_id);
    if ($isChild && isset($slotData->lesson->parentLesson)) {
        $parent = $slotData->lesson->parentLesson;
        $popoverTitle = "Birleştirilmiş Ders";
        $popoverContent = "Bu ders " . $parent->getFullName(addCode: true, addProgram: true) . " dersine bağlı olduğu için düzenlenemez.";
        $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
    }
}
?>
<div <?= $attrString ?> <?= $popoverAttr ?> role="button" aria-grabbed="false" tabindex="0">
    <?php if ((!isset($only_table) || !$only_table) && (!isset($preference_mode) || !$preference_mode)): ?>
        <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
    <?php endif; ?>

    <span class="lesson-name">
        <?php if ($type === 'exam'): ?>
            <?= $slotData->lesson->getFullName(addProgram: true) ?>
        <?php else: ?>
            <?php if ($schedule->owner_type !== 'program'): ?>
                <?= $slotData->lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: true) ?>
            <?php else: ?>
                <?= $slotData->lesson->getFullName(addGroup: true) ?>
            <?php endif; ?>
        <?php endif; ?>
    </span>

    <div class="lesson-meta flex-wrap">
        <?php if ($type === 'exam' && isset($scheduleItem->detail['assignments']) && is_array($scheduleItem->detail['assignments'])): ?>
            <div class="lesson-observers-list w-100">
                <?php foreach ($scheduleItem->detail['assignments'] as $assignment): ?>
                    <div class="lesson-observer-item small d-flex justify-content-between w-100">
                        <span class="lesson-lecturer text-truncate" title="Gözetmen">
                            <?= $assignment['observer_name'] ?>
                        </span>
                        <span class="lesson-classroom fw-bold ms-2" title="Derslik">
                            <?= $assignment['classroom_name'] ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between w-100">
                <span class="lesson-lecturer">
                    <?= $slotData->lecturer?->getFullName() ?>
                </span>
                <span class="lesson-classroom">
                    <?= $slotData->classroom?->name ?>
                </span>
            </div>
        <?php endif; ?>
        <?php if (!($schedule->owner_type == 'user' && $type == "exam")): ?>
            <?= View::renderComponent('schedules/_childLessons', [
                'slotData' => $slotData
            ]) ?>
        <?php endif; ?>
    </div>
</div>