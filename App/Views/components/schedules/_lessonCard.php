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

$lessonName = ($type === 'exam')
    ? (($schedule->owner_type !== 'program') ? $slotData->lesson->getFullName(addProgram: true, addClassNumber: true) : $slotData->lesson->getFullName())
    : (($schedule->owner_type !== 'program') ? $slotData->lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: true) : $slotData->lesson->getFullName(addGroup: true));
?>
<div <?= $attrString ?> <?= $popoverAttr ?> role="button" aria-grabbed="false" tabindex="0">
    <div class="d-flex align-items-start justify-content-between gap-1 w-100 mb-1">
        <?php if (!$isOnlyTable && !$isPreferenceMode && !$isLocked): ?>
            <input type="checkbox" class="lesson-bulk-checkbox mt-1" title="Toplu işlem için seç">
        <?php endif; ?>

        <span class="lesson-name flex-grow-1" title="<?= htmlspecialchars($lessonName) ?>">
            <?php if (!$isOnlyTable && $isLocked): ?>
                <i class="fa fa-lock me-1 text-danger" title="Kilitli"></i>
            <?php endif; ?>
            <?= $lessonName ?>
        </span>
    </div>

    <div class="lesson-meta flex-wrap w-100">
        <?php if ($type === 'exam' && isset($scheduleItem->detail['assignments']) && is_array($scheduleItem->detail['assignments'])): ?>
            <div class="lesson-observers-list w-100 d-flex flex-column gap-1">
                <?php foreach ($scheduleItem->detail['assignments'] as $assignment): 
                    $observerNamesList = [];
                    if (!empty($assignment['observers']) && is_array($assignment['observers'])) {
                        $observerNamesList = array_values(array_filter(array_map(fn($o) => is_array($o) ? ($o['name'] ?? '') : (is_string($o) ? $o : ''), $assignment['observers'])));
                    } elseif (!empty($assignment['observer_name'])) {
                        $observerNamesList = [$assignment['observer_name']];
                    }
                    if (empty($observerNamesList)) {
                        $observerNamesList = ['Gözetmen atanmadı'];
                    }
                ?>
                    <div class="lesson-observer-item small d-flex align-items-center justify-content-between w-100 gap-1">
                        <div class="d-flex flex-column flex-grow-1 min-w-0">
                            <?php foreach ($observerNamesList as $obsName): ?>
                                <span class="lesson-lecturer text-truncate" title="Gözetmen: <?= htmlspecialchars($obsName) ?>">
                                    <i class="bi bi-person-badge me-1 opacity-75"></i><?= htmlspecialchars($obsName) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($assignment['classroom_name'])): ?>
                            <span class="lesson-classroom lesson-classroom-badge ms-1 align-self-center flex-shrink-0" title="Sınav Salonu: <?= htmlspecialchars($assignment['classroom_name']) ?>">
                                <?= htmlspecialchars($assignment['classroom_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-between w-100">
                <span class="lesson-lecturer text-truncate" title="<?= htmlspecialchars(($slotData->lecturer ?? null)?->getFullName() ?? '') ?>">
                    <i class="bi bi-person me-1 opacity-75"></i><?= ($slotData->lecturer ?? null)?->getFullName() ?>
                </span>
                <?php if (!empty(($slotData->classroom ?? null)?->name)): ?>
                    <span class="lesson-classroom lesson-classroom-badge ms-1" title="Derslik: <?= htmlspecialchars($slotData->classroom->name) ?>">
                        <?= $slotData->classroom->name ?>
                    </span>
                <?php else: ?>
                    <span class="lesson-classroom" title="Derslik atanmadı"></span>
                <?php endif; ?>
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