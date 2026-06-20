<?php
/**
 * Bağlı dersler (child lessons) partial'ı
 *
 * Hem ders hem sınav programı tablolarında ortak kullanılır.
 * Sınav programında examChildLessons, ders programında childLessons gösterilir.
 *
 * Beklenen değişkenler:
 * @var object|null $slotData  Slot verisi (opsiyonel, tablo için - lesson içerir)
 * @var \App\Models\Lesson|null $lesson  Doğrudan lesson nesnesi (opsiyonel, available panel için)
 * @var string $type  'exam' veya 'lesson' (varsayılan: 'lesson')
 */
?>
<?php
$targetLesson = isset($lesson) ? $lesson : $slotData->lesson;
$scheduleType = $type ?? 'lesson';
$isExam = in_array($scheduleType, ['exam', 'midterm-exam', 'final-exam', 'makeup-exam']);

// Sınav programında examChildLessons, ders programında childLessons
$children = $isExam
    ? ($targetLesson->examChildLessons ?? [])
    : ($targetLesson->childLessons ?? []);

$label = $isExam ? 'Sınav Bağlı Dersler' : 'Bağlı Dersler';

if (!empty($children)):
?>
    <div class="lesson-observers-list w-100 mt-1 border-top pt-1">
        <small class="d-block text-muted" style="font-size: 0.7rem; margin-bottom: 2px;"><?= $label ?></small>
        <?php foreach ($children as $child): 
            if ($child->program): 
            $childName = $child->getFullName(addGroup: true, addProgram: true, addClassNumber: true);?>
                <div class="lesson-observer-item small d-flex justify-content-between w-100">
                    <span class="lesson-lecturer text-truncate" title="<?= htmlspecialchars($childName) ?>">
                        <?= htmlspecialchars($childName) ?>
                    </span>
                </div>
        <?php endif;
         endforeach; ?>
    </div>
<?php
endif; 
?>
