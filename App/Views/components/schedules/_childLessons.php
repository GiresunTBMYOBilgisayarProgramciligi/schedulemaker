<?php
/**
 * Bağlı dersler (child lessons) partial'ı
 *
 * Hem ders hem sınav programı tablolarında ortak kullanılır.
 *
 * Beklenen değişkenler:
 * @var object $slotData  Slot verisi (lesson içerir)
 */
?>
<?php
if (!empty($slotData->lesson->childLessons)):
?>
    <div class="lesson-observers-list w-100 mt-1 border-top pt-1">
        <small class="d-block text-muted" style="font-size: 0.7rem; margin-bottom: 2px;">Bağlı Dersler</small>
        <?php foreach ($slotData->lesson->childLessons as $child): 
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
