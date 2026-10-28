<?php
use App\Core\View;
use App\Enums\OwnerType;
use App\Helpers\ScheduleViewHelper;
use App\Services\Schedule\GuestScheduleVisibility;
use function App\Helpers\e;
use function App\Helpers\getClassFromSemesterNo;

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
// Misafirlere gözetmen bilgisi gösterilmez (bkz. GuestScheduleVisibility kural 2 ve 3)
$hideObservers = GuestScheduleVisibility::hidesObservers();
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
            $popoverAttr = 'data-bs-toggle="popover" title="' . e($popoverTitle) . '" data-bs-content="' . e($popoverContent) . '" data-bs-trigger="hover"';
        }
    } elseif ($type === 'exam') {
        $isChild = !empty($slotData->lesson->examParentLesson);
        if ($isChild && isset($slotData->lesson->examParentLesson)) {
            $parent = $slotData->lesson->examParentLesson;
            $popoverTitle = "Sınav Birleştirmesi";
            $popoverContent = "Bu dersin sınavı, " . $parent->getFullName(addCode: true, addProgram: true) . " dersine bağlıdır.";
            $popoverAttr = 'data-bs-toggle="popover" title="' . e($popoverTitle) . '" data-bs-content="' . e($popoverContent) . '" data-bs-trigger="hover"';
        }
    }

    if ($isLocked) {
        $popoverTitle = isset($popoverTitle) ? "Kilitli & " . $popoverTitle : "Kilitli Öğe";
        $popoverContent = isset($popoverContent) 
            ? "Bu öğe kilitlidir ve düzenlenemez. Ayrıca: " . $popoverContent 
            : "Bu öğe kilitlenmiştir. Kilidi açılana kadar düzenlenemez.";
        $popoverAttr = 'data-bs-toggle="popover" title="' . e($popoverTitle) . '" data-bs-content="' . e($popoverContent) . '" data-bs-trigger="hover"';
    }
}

$isUserSchedule = ($schedule->owner_type === OwnerType::USER->value);
$isClassroomSchedule = ($schedule->owner_type === OwnerType::CLASSROOM->value);
$isProgramSchedule = ($schedule->owner_type === OwnerType::PROGRAM->value);

$groupLetter = $slotData->lesson->getGroupLetter();
// Birleştirilmiş grup kardeşleri (hoca programı): Grup A, B
$groupLabel = $scheduleItem->getGroupLabel($slotData->lesson);

$fullLessonTitle = ($type === 'exam')
    ? (($schedule->owner_type !== 'program') ? $slotData->lesson->getFullName(addProgram: true, addClassNumber: true) : $slotData->lesson->getFullName())
    : (($schedule->owner_type !== 'program') ? $slotData->lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: true) : $slotData->lesson->getFullName(addGroup: true));

// Gözetmenlik görevi: hocanın kendi user programındaki, ders sahibi olmayan sınav kaydı
$isObserverDuty = $type === 'exam' && $isUserSchedule
    && ($scheduleItem->detail['reference_type'] ?? null) === 'exam_assignment';

// Gözetmenlik kaydının ve misafir sınav görünümünün ders hocası (ExamScheduleService::attachLessonLecturerNames ile eklenir)
$lessonLecturerName = ($isObserverDuty || ($type === 'exam' && $hideObservers)) ? ($scheduleItem->detail['lesson_lecturer'] ?? null) : null;

$lessonDisplayName = ($type === 'exam')
    ? (($isClassroomSchedule || $isProgramSchedule) ? $slotData->lesson->getFullName() : $slotData->lesson->getFullName(addProgram: true, addClassNumber: true))
    : (($isUserSchedule || $isClassroomSchedule || $isProgramSchedule) ? $slotData->lesson->getFullName(addGroup: false) : $slotData->lesson->getFullName(addProgram: true, addClassNumber: true, addGroup: false));

$programInfo = null;
if (($isUserSchedule || $isClassroomSchedule) && !empty($slotData->lesson->program)) {
    $progName = $slotData->lesson->program->name;
    $classNo = getClassFromSemesterNo($slotData->lesson->semester_no);
    $programInfo = $progName . ($classNo ? " - {$classNo}. Sınıf" : "");
}
?>
<div <?= $attrString ?> <?= $popoverAttr ?> role="button" aria-grabbed="false" tabindex="0">
    <div class="d-flex align-items-start justify-content-between gap-1 w-100 mb-1 lesson-title-row">
        <div class="d-flex align-items-start gap-1 min-w-0 flex-grow-1">
            <?php if (!$isOnlyTable && !$isPreferenceMode && !$isLocked): ?>
                <input type="checkbox" class="lesson-bulk-checkbox mt-0" title="Toplu işlem için seç">
            <?php endif; ?>

            <span class="lesson-name" title="<?= e($fullLessonTitle) ?>">
                <?php if (!$isOnlyTable && $isLocked): ?>
                    <i class="fa fa-lock me-1 text-danger" title="Kilitli"></i>
                <?php endif; ?>
                <?= e($lessonDisplayName) ?>
            </span>
        </div>

        <?php if ($isObserverDuty): ?>
            <span class="badge text-bg-warning flex-shrink-0" title="<?= $hideObservers ? 'Bu sınavda gözetmen olarak görevli' : 'Bu sınavda gözetmen olarak görevlisiniz' ?>">Gözetmenlik</span>
        <?php endif; ?>

        <?php if ($groupLetter !== null): ?>
            <span class="badge lesson-group-badge lesson-group-badge-<?= e(strtolower($groupLetter)) ?> flex-shrink-0" title="Grup <?= e($groupLabel) ?>">
                Grup <?= e($groupLabel) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="lesson-meta flex-wrap w-100">
        <?php if ($type === 'exam' && $hideObservers && isset($scheduleItem->detail['assignments']) && is_array($scheduleItem->detail['assignments'])): ?>
            <?php // Misafir görünümü: gözetmen satırları yerine ders hocası ve salonlar (rozet) gösterilir ?>
            <?php $examClassroomNames = array_values(array_unique(array_filter(array_map(
                fn($a) => is_array($a) ? (string)($a['classroom_name'] ?? '') : '',
                $scheduleItem->detail['assignments']
            )))); ?>
            <div class="d-flex flex-column w-100 gap-1">
                <?php if (!empty($lessonLecturerName)): ?>
                    <span class="lesson-lecturer text-truncate" title="Ders hocası: <?= e($lessonLecturerName) ?>">
                        <i class="bi bi-person me-1 opacity-75"></i><?= e($lessonLecturerName) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($examClassroomNames)): ?>
                    <div class="lesson-exam-classrooms w-100 d-flex flex-wrap align-items-center gap-1" title="Sınav Salonları: <?= e(implode(', ', $examClassroomNames)) ?>">
                        <i class="bi bi-door-open opacity-75"></i>
                        <?php foreach ($examClassroomNames as $classroomName): ?>
                            <span class="lesson-classroom lesson-classroom-badge"><?= e($classroomName) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($type === 'exam' && isset($scheduleItem->detail['assignments']) && is_array($scheduleItem->detail['assignments'])): ?>
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
                                <span class="lesson-lecturer text-truncate" title="Gözetmen: <?= e($obsName) ?>">
                                    <i class="bi bi-person-badge me-1 opacity-75"></i><?= e($obsName) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($assignment['classroom_name'])): ?>
                            <span class="lesson-classroom lesson-classroom-badge ms-1 align-self-center flex-shrink-0" title="Sınav Salonu: <?= e($assignment['classroom_name']) ?>">
                                <?= e($assignment['classroom_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php if ($isClassroomSchedule): ?>
                <div class="d-flex flex-column w-100 gap-1 lesson-meta-classroom">
                    <?php // Sınav derslik kaydında lecturer gözetmendir; misafirde temizlendiği için yerine ders hocası gösterilir ?>
                    <?php $classroomCardLecturer = ($slotData->lecturer ?? null)?->getFullName() ?? $lessonLecturerName; ?>
                    <?php if (!empty($classroomCardLecturer)): ?>
                        <span class="lesson-lecturer text-truncate" title="<?= e($classroomCardLecturer) ?>">
                            <i class="bi bi-person me-1 opacity-75"></i><?= e($classroomCardLecturer) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($programInfo)): ?>
                        <span class="lesson-program text-truncate" title="Program: <?= e($programInfo) ?>">
                            <i class="bi bi-mortarboard me-1 opacity-75"></i><?= e($programInfo) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($lessonLecturerName)): ?>
                <?php // Gözetmenlik: ders hocası ve sınav salonu ayrı satırlarda gösterilir ?>
                <div class="d-flex flex-column w-100 gap-1 lesson-meta-observer-duty">
                    <span class="lesson-lecturer text-truncate" title="Ders hocası: <?= e($lessonLecturerName) ?>">
                        <i class="bi bi-person-badge me-1 opacity-75"></i>Hoca: <?= e($lessonLecturerName) ?>
                    </span>
                    <?php if (!empty(($slotData->classroom ?? null)?->name)): ?>
                        <span class="lesson-classroom text-truncate" title="Sınav salonu: <?= e($slotData->classroom->name) ?>">
                            <i class="bi bi-door-open me-1 opacity-75"></i>Salon: <strong><?= e($slotData->classroom->name) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-between w-100 lesson-meta-row">
                    <?php if ($isUserSchedule && !empty($programInfo)): ?>
                        <span class="lesson-program text-truncate" title="Program: <?= e($programInfo) ?>">
                            <i class="bi bi-mortarboard me-1 opacity-75"></i><?= e($programInfo) ?>
                        </span>
                    <?php else: ?>
                        <span class="lesson-lecturer text-truncate" title="<?= e(($slotData->lecturer ?? null)?->getFullName() ?? '') ?>">
                            <i class="bi bi-person me-1 opacity-75"></i><?= e(($slotData->lecturer ?? null)?->getFullName() ?? '') ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty(($slotData->classroom ?? null)?->name)): ?>
                        <span class="lesson-classroom lesson-classroom-badge ms-1 flex-shrink-0" title="Derslik: <?= e($slotData->classroom->name) ?>">
                            <?= e($slotData->classroom->name) ?>
                        </span>
                    <?php else: ?>
                        <span class="lesson-classroom" title="Derslik atanmadı"></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!$isOnlyTable && !$isObserverDuty): ?>
            <?= View::renderComponent('schedules/_childLessons', [
                'slotData' => $slotData,
                'type' => $type
            ]) ?>
        <?php endif; ?>
    </div>
</div>