<?php
use App\Core\View;
use App\Models\Lesson;
use App\Models\Schedule;

/**
 * Programa atanmamış (available) derslerin listesi.
 *
 * Her bir ders kartı _availableLessonCard component'i ile render edilir.
 *
 * @var array $availableLessons  Lesson modelleri ve/veya dummy objeler
 * @var Schedule $schedule       Üst schedule nesnesi
 */
?>
<div class="row available-schedule-items drop-zone small" data-bs-toggle="tooltip" title="Silmek için buraya sürükleyin"
    data-bs-placement="left" data-bs-trigger="none">
    <?php foreach ($availableLessons as $lesson): ?>
        <?= View::renderComponent('schedules/_availableLessonCard', [
            'lesson' => $lesson,
            'schedule' => $schedule,
            'isDummy' => isset($lesson->is_dummy) && $lesson->is_dummy,
        ]) ?>
    <?php endforeach; ?>
</div><!--end::available-schedule-items-->