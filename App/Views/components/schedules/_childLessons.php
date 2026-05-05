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
<span class="child-lessons">
    <?php
    $childLessonNames = [];

    if (!empty($slotData->lesson->childLessons)) {
        echo "<small>Bağlı Dersler</small> <br>";
        foreach ($slotData->lesson->childLessons as $child) {
            if ($child->program) {
                $childLessonNames[] = $child->getFullName(addGroup: true, addProgram: true, addClassNumber: true);
            }
        }
    }
    // Unique ve virgülle birleştir
    $childLessonNamesStr = implode('<br>', array_unique($childLessonNames));

    echo $childLessonNamesStr ? " ($childLessonNamesStr)" : "";
    ?>
</span>
