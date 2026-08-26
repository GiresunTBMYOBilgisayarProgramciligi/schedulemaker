<?php
use App\Core\View;
use App\Enums\OwnerType;
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

$isOnlyTable = !empty($only_table);
$isPreferenceMode = !empty($preference_mode);
$isLocked = !empty($scheduleItem->detail['is_locked']);

$attrString = ScheduleViewHelper::renderAttributes(
    ScheduleViewHelper::buildLessonCardAttributes(
        $scheduleItem,
        $slotData,
        $schedule,
        $draggable,
        $type,
        $isOnlyTable
    )
);

$popoverAttr = "";
if (!$isOnlyTable) {
    if ($type === 'lesson') {
        $isChild = !empty($slotData->lesson->parentLesson);
        if ($isChild && isset($slotData->lesson->parentLesson)) {
            $parent = $slotData->lesson->parentLesson;
            $popoverTitle = "Birleştirilmiş Ders";
            $popoverContent = "Bu ders " . $parent->getFullName(addCode: true, addProgram: true) . " dersine bağlı olduğu için düzenlenemez.";
            $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
        }
    } elseif ($type === 'exam') {
        $isChild = !empty($slotData->lesson->examParentLesson);
        if ($isChild && isset($slotData->lesson->examParentLesson)) {
            $parent = $slotData->lesson->examParentLesson;
            $popoverTitle = "Sınav Birleştirmesi";
            $popoverContent = "Bu dersin sınavı, " . $parent->getFullName(addCode: true, addProgram: true) . " dersine bağlıdır.";
            $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
        }
    }

    if ($isLocked) {
        $popoverTitle = isset($popoverTitle) ? "Kilitli & " . $popoverTitle : "Kilitli Öğe";
        $popoverContent = isset($popoverContent) 
            ? "Bu öğe kilitlidir ve düzenlenemez. Ayrıca: " . $popoverContent 
            : "Bu öğe kilitlenmiştir. Kilidi açılana kadar düzenlenemez.";
        $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
    }
}
?>
<div <?= $attrString ?> <?= $popoverAttr ?> role="button" aria-grabbed="false" tabindex="0">
    <?php if (!$isOnlyTable && !$isPreferenceMode && !$isLocked): ?>
        <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
    <?php endif; ?>

    <span class="lesson-name">
        <?php if (!$isOnlyTable && $isLocked): ?>
            <i class="fa fa-lock me-1" title="Kilitli"></i>
        <?php endif; ?>
        <?php if ($type === 'exam'): ?>
            <?php if ($schedule->owner_type !== 'program'): ?>
                <?= $slotData->lesson->getFullName(addProgram: true, addClassNumber: true) ?>
            <?php else: ?>
                <?= $slotData->lesson->getFullName() ?>
            <?php endif; ?>
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
                <span class="lesson-lecturer" title="<?= htmlspecialchars(($slotData->lecturer ?? null)?->getFullName() ?? '') ?>">
                    <?= ($slotData->lecturer ?? null)?->getFullName() ?>
                </span>
                <span class="lesson-classroom" title="<?= htmlspecialchars(($slotData->classroom ?? null)?->name ?? '') ?>">
                    <?= ($slotData->classroom ?? null)?->name ?>
                </span>
            </div>
        <?php endif; ?>
        <?php if (!$isOnlyTable && !($schedule->owner_type == OwnerType::USER->value && $type == "exam")): ?>
            <?= View::renderComponent('schedules/_childLessons', [
                'slotData' => $slotData,
                'type' => $type
            ]) ?>
        <?php endif; ?>
    </div>
</div>