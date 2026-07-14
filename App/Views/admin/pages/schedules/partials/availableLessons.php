<?php
use App\Enums\ClassroomType;
use App\Enums\ExamType;
use App\Core\View;
use App\Models\Lesson;
use App\Models\Schedule;

/**
 * Programa atanmamış (available) derslerin listesi.
 *
 * Dersler classroom_type'a göre gruplandırılarak collapse (accordion) ile gösterilir.
 * Dummy kartlar (preferred/unavailable) gruplandırma dışında en üstte gösterilir.
 *
 * @var array $availableLessons  Lesson modelleri ve/veya dummy objeler
 * @var Schedule $schedule       Üst schedule nesnesi
 */

// Dersleri classroom_type'a göre grupla, dummy'leri ayır
$groupedLessons = [];
$dummyLessons = [];
$classroomTypes = ClassroomType::toArray();

foreach ($availableLessons as $lesson) {
    $isDummy = isset($lesson->is_dummy) && $lesson->is_dummy;
    if ($isDummy) {
        $dummyLessons[] = $lesson;
    } else {
        $type = $lesson->classroom_type ?? 0;
        $groupedLessons[$type][] = $lesson;
    }
}

// classroom_type key'lerine göre sırala (1, 2, 3, 4)
ksort($groupedLessons);

$accordionId = 'availableLessonsAccordion-' . $schedule->id;
?>
<div class="available-schedule-items drop-zone small" data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin"
    data-bs-placement="left" data-bs-trigger="none" data-overlayscrollbars-initialize data-overlayscrollbars-overflow-x="hidden">

    <?php if (empty($availableLessons)): ?>
        <?php $isExam = ExamType::isExamType($schedule->type ?? ''); ?>
        <div class="alert alert-warning m-2 p-2" role="alert">
            <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle"></i> Uygun Ders Bulunamadı!</h6>
            <p class="mb-0" style="font-size: 0.85rem;">
                Eğer bir hata olduğunu düşünüyorsanız lütfen 
                <?php if ($isExam): ?>
                    ilgili derslerin <strong>mevcudunu</strong> kontrol edin. Sınav programı oluşturulabilmesi için ders mevcudunun girilmiş olması gerekir.
                <?php else: ?>
                    ilgili derslerin <strong>saatini</strong> kontrol edin. Ders programı oluşturulabilmesi için ders saatinin girilmiş olması gerekir.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php // Dummy kartlar (grouped dışında) ?>
    <?php if (!empty($dummyLessons)): ?>
        <div class="row mb-1">
            <?php foreach ($dummyLessons as $lesson): ?>
                <?= View::renderComponent('schedules/_availableLessonCard', [
                    'lesson' => $lesson,
                    'schedule' => $schedule,
                    'isDummy' => true,
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php // Derslik türüne göre gruplandırılmış dersler ?>
    <div class="accordion accordion-flush" id="<?= $accordionId ?>">
        <?php foreach ($groupedLessons as $typeKey => $lessons): ?>
            <?php
            $typeName = $classroomTypes[$typeKey] ?? 'Diğer';
            $collapseId = 'collapse-type-' . $typeKey . '-' . $schedule->id;
            $lessonCount = count($lessons);
            ?>
            <div class="accordion-item available-lessons-group">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-1 px-2" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                        aria-expanded="false" aria-controls="<?= $collapseId ?>">
                        <span class="badge bg-secondary me-2"><?= $lessonCount ?></span>
                        <?= htmlspecialchars($typeName) ?>
                    </button>
                </h2>
                <div id="<?= $collapseId ?>" class="accordion-collapse collapse show"
                    data-bs-parent="#<?= $accordionId ?>">
                    <div class="accordion-body p-1">
                        <div class="row">
                            <?php foreach ($lessons as $lesson): ?>
                                <?= View::renderComponent('schedules/_availableLessonCard', [
                                    'lesson' => $lesson,
                                    'schedule' => $schedule,
                                    'isDummy' => false,
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div><!--end::available-schedule-items-->