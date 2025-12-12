<?php
use App\Models\Lesson;
use App\Models\User;
use App\Models\Classroom;
use function App\Helpers\getSettingValue;

/**
 * @var array $filters
 * @var array $scheduleRows
 */

$createTableHeaders = function () use ($filters): string {
    $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
    $headers = '<th style="width: 7%;">#</th>';
    $maxDayIndex = ($filters['type'] === 'exam')
        ? getSettingValue('maxDayIndex', 'exam', 5)
        : getSettingValue('maxDayIndex', 'lesson', 4);
    for ($i = 0; $i <= $maxDayIndex; $i++) {
        $headers .= '<th>' . $days[$i] . '</th>';
    }
    return $headers;
};

// eğer semerser_no dizi ise dönemler birleştirilmiş demektir.
$semester_no = (isset($filters['semester_no']) and !is_array($filters['semester_no'])) ? 'data-semester-no="' . $filters['semester_no'] . '"' : "";
// eğer dönem belirtilmemişse aktif dönem bilgisi alınır
$semester = 'data-semester="' . $filters['semester'] . '"';

/**
 * Dersin saatlari ayrı ayrı eklendiği için ve her ders parçasının ayrı bir id değerinin olması için dersin saat sayısı bilgisini tutar
 */
$lessonHourCount = [];
?>
<table class="table table-bordered table-sm small" <?= $semester_no ?> <?= $semester ?>>
    <thead>
        <tr>
            <?= $createTableHeaders() ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $times = array_keys($scheduleRows);
        for ($i = 0; $i < count($times); $i++):
            $tableRow = $scheduleRows[$times[$i]];
            ?>
            <tr>
                <td>
                    <?= $times[$i] ?>
                </td>
                <?php
                $dayIndex = 0;
                foreach ($tableRow as $day):
                    /*
                     * Eğer bir ders kaydedilmişse day true yada false değildir. Dizi olarak ders sınıf ve hoca bilgisini tutar
                     */
                    if (is_array($day)):
                        if (isset($day[0]) and is_array($day[0])):
                            //gün içerisinde iki ders var
                            ?>
                            <td class="drop-zone">
                                <?php foreach ($day as $column):
                                    $column = (object) $column; // Array'i objeye dönüştür
                                    $lesson = (new Lesson())->find($column->lesson_id) ?: throw new Exception("Ders bulunamdı");
                                    $lessonHourCount[$lesson->id] = !isset($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                                    $lecturer = (new User())->find($column->lecturer_id);
                                    $classroom = (new Classroom())->find($column->classroom_id);
                                    $draggable = "true";
                                    if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
                                        $draggable = "false";
                                    }
                                    $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
                                    $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
                                    $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . $parentLesson->getProgram()->name . ') dersine bağlı olduğu için düzenlenemez."';
                                    /**
                                     * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
                                     */
                                    $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . $lesson->getProgram()->name . ')' : $lesson->name;
                                    ?>
                                    <div id="scheduleTable-lesson-<?= $column->lesson_id . '-' . $lessonHourCount[$lesson->id] ?>"
                                        draggable="<?= $draggable ?>"
                                        class="d-flex justify-content-between align-items-start mb-1 p-2 rounded <?= $text_bg ?>"
                                        data-lesson-code="<?= $lesson->code ?>" data-semester-no="<?= $lesson->semester_no ?>"
                                        data-lesson-id="<?= $lesson->id ?>" data-lecturer-id="<?= $lecturer->id ?>"
                                        data-time="<?= $times[$i] ?>" data-day-index="<?= $dayIndex ?>" <?= $semester ?>                     <?= $popover ?>
                                        data-academic-year="<?= $lesson->academic_year ?>" data-classroom-id="<?= $classroom->id ?>"
                                        data-lesson-hours="<?= $lesson->hours ?>" data-size="<?= ($lesson->size ?? 0) ?>"
                                        data-classroom-exam-size="<?= ($classroom->exam_size ?? 0) ?>"
                                        data-classroom-size="<?= ($classroom->size ?? 0) ?>">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold lesson-title" data-bs-toggle="tooltip" data-bs-placement="left"
                                                title="<?= $lesson->code ?>">
                                                <a class="link-light link-underline-opacity-0" target="_blank"
                                                    href="/admin/lesson/<?= $lesson->id ?>">
                                                    <i class="bi bi-book"></i>
                                                </a>
                                                <?= $lessonName ?>
                                            </div>
                                            <div class="text-nowrap lecturer-title" id="lecturer-<?= $lecturer->id ?>">
                                                <a class="link-light link-underline-opacity-0" target="_blank"
                                                    href="/admin/profile/<?= $lecturer->id ?>">
                                                    <i class="bi bi-person-square"></i>
                                                </a>
                                                <?= $lecturer->getFullName() ?>
                                            </div>
                                        </div>
                                        <a href="/admin/classroom/<?= $classroom->id ?>" class="link-light link-underline-opacity-0"
                                            target="_blank">
                                            <span id="classroom-<?= $classroom->id ?>" class="badge bg-info rounded-pill">
                                                <i class="bi bi-door-open"></i> <?= $classroom->name ?>
                                            </span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        <?php else:
                            // Eğer day bir array ise bilgileri yazdır
                            $day = (object) $day; // Array'i objeye dönüştür
                            $lesson = (new Lesson())->find($day->lesson_id) ?: throw new Exception("Ders bulunamdı");
                            $lessonHourCount[$lesson->id] = !isset($lessonHourCount[$lesson->id]) ? 1 : $lessonHourCount[$lesson->id] + 1;
                            $lecturer = (new User())->find($day->lecturer_id);
                            $classroom = (new Classroom)->find($day->classroom_id);
                            $draggable = "true";
                            if (!is_null($lesson->parent_lesson_id) or getSettingValue("academic_year") != $filters['academic_year'] or getSettingValue("semester") != $filters['semester']) {
                                $draggable = "false";
                            }
                            $text_bg = is_null($lesson->parent_lesson_id) ? "text-bg-primary" : "text-bg-secondary";
                            $parentLesson = is_null($lesson->parent_lesson_id) ? null : (new Lesson())->find($lesson->parent_lesson_id);
                            $badgeCSS = is_null($lesson->parent_lesson_id) ? "bg-info" : "bg-light text-dark";
                            $popover = is_null($lesson->parent_lesson_id) ? "" : 'data-bs-toggle="popover" title="Birleştirilmiş Ders" data-bs-content="Bu ders ' . $parentLesson->getFullName() . '(' . $parentLesson->getProgram()->name . ') dersine bağlı olduğu için düzenlenemez."';
                            /**
                             * Eğer hoca yada derslik programı ise Ders adının sonuna program bilgisini ekle
                             */
                            $lessonName = in_array($filters['owner_type'], ['user', 'classroom']) ? $lesson->name . ' (' . $lesson->getProgram()->name . ')' : $lesson->name;
                            ?>
                            <td class="drop-zone">
                                <div id="scheduleTable-lesson-<?= $lesson->id . '-' . $lessonHourCount[$lesson->id] ?>"
                                    draggable="<?= $draggable ?>"
                                    class="d-flex justify-content-between align-items-start mb-1 p-2 rounded <?= $text_bg ?>"
                                    data-lesson-code="<?= $lesson->code ?>" data-semester-no="<?= $lesson->semester_no ?>"
                                    data-lesson-id="<?= $lesson->id ?>" data-lecturer-id="<?= $lecturer->id ?>"
                                    data-time="<?= $times[$i] ?>" data-day-index="<?= $dayIndex ?>" <?= $semester ?>                 <?= $popover ?>
                                    data-academic-year="<?= $lesson->academic_year ?>" data-classroom-id="<?= $classroom->id ?>"
                                    data-lesson-hours="<?= $lesson->hours ?>" data-size="<?= ($lesson->size ?? 0) ?>"
                                    data-classroom-exam-size="<?= ($classroom->exam_size ?? 0) ?>"
                                    data-classroom-size="<?= ($classroom->size ?? 0) ?>">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold lesson-title" data-bs-toggle="tooltip" data-bs-placement="left"
                                            title="<?= $lesson->code ?>">
                                            <a class="link-light link-underline-opacity-0" target="_blank"
                                                href="/admin/lesson/<?= $lesson->id ?>">
                                                <i class="bi bi-book"></i>
                                            </a>
                                            <?= $lessonName ?>

                                        </div>
                                        <div class="text-nowrap lecturer-title" id="lecturer-<?= $lecturer->id ?>">
                                            <a class="link-light link-underline-opacity-0" target="_blank"
                                                href="/admin/profile/<?= $lecturer->id ?>">
                                                <i class="bi bi-person-square"></i>
                                            </a>
                                            <?= $lecturer->getFullName() ?>
                                        </div>
                                    </div>
                                    <a href="/admin/classroom/<?= $classroom->id ?>" class="link-light link-underline-opacity-0"
                                        target="_blank">
                                        <span id="classroom-<?= $classroom->id ?>" class="badge <?= $badgeCSS ?> rounded-pill">
                                            <i class="bi bi-door-open"></i> <?= $classroom->name ?>
                                        </span>
                                    </a>
                                </div>
                            </td>
                        <?php endif;
                    elseif (is_null($day)):
                        // Eğer null veya true ise boş dropzone ekle
                        echo ($times[$i] === "12.00 - 12.50")
                            ? '<td class="bg-danger"></td>' // Öğle saatinde kırmızı hücre
                            : '<td class="drop-zone"></td>';
                    elseif ($day === true):
                        echo '<td class="bg-success"></td>';
                    else:
                        // Eğer false ise kırmızı vurgulu hücre ekle
                        echo '<td class="bg-danger"></td>';
                    endif;
                    $dayIndex++;
                endforeach; ?>
            </tr>
        <?php endfor; ?>
    </tbody>
</table>