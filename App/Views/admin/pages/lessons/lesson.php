<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var Lesson $lesson
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var string $page_title
 * @var string $scheduleHTML
 * @var array $combineLessonList
 * @var array $examCombineLessonList
 */

use App\Models\Lesson;
use App\Core\Gate;

?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= $page_title ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Ders</li>
                    </ol>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-9">
                    <!-- Ders Bilgileri -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Ders Bilgileri</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-2">Ders Kodu - Grup No</dt>
                                <dd class="col-sm-4"><?= $lesson->code ?> - <?= $lesson->group_no ?></dd>
                                <dt class="col-sm-2">Ders Adı</dt>
                                <dd class="col-sm-4"><?= $lesson->name ?></dd>
                                <dt class="col-sm-2">Ders Türü</dt>
                                <dd class="col-sm-4"><?= $lesson->getTypeName() ?></dd>
                                <dt class="col-sm-2">Saat</dt>
                                <dd class="col-sm-4"><?= $lesson->hours ?></dd>
                                <dt class="col-sm-2">Yarıyılı</dt>
                                <dd class="col-sm-4"><?= $lesson->semester_no . ". Yarıyıl" ?></dd>
                                <dt class="col-sm-2">Bölüm</dt>
                                <dd class="col-sm-4">
                                    <a href="/admin/department/<?= $lesson->department_id ?>">
                                        <?= $lesson->department->name ?>
                                    </a>
                                </dd>
                                <dt class="col-sm-2">Program</dt>
                                <dd class="col-sm-4">
                                    <a href="/admin/program/<?= $lesson->program_id ?>">
                                        <?= $lesson->program->name ?>
                                    </a>
                                </dd>
                                <dt class="col-sm-2">Derslik Türü</dt>
                                <dd class="col-sm-4"><?= $lesson->getClassroomTypeName() ?></dd>
                                <dt class="col-sm-2">Akademik yıl ve Dönem</dt>
                                <dd class="col-sm-4"><?= $lesson->academic_year . " " . $lesson->semester ?></dd>
                                <dt class="col-sm-2">Mevcudu</dt>
                                <dd class="col-sm-4"><?= $lesson->size ?></dd>
                                <?php
                                // ── Bağlı Olduğu Ders (parent_lesson_id + exam_parent_lesson_id) ──
                                $parentLinks = [];
                                if ($lesson->parentLesson) {
                                    $parentLinks[$lesson->parentLesson->id] = [
                                        'lesson' => $lesson->parentLesson,
                                        'types' => ['lesson'],
                                        'actions' => ['lesson' => '/ajax/deleteParentLesson']
                                    ];
                                }
                                if ($lesson->examParentLesson) {
                                    $epId = $lesson->examParentLesson->id;
                                    if (isset($parentLinks[$epId])) {
                                        // Aynı ders hem ders hem sınav parent'ı — iki badge göster
                                        $parentLinks[$epId]['types'][] = 'exam';
                                        $parentLinks[$epId]['actions']['exam'] = '/ajax/deleteExamParentLesson';
                                    } else {
                                        $parentLinks[$epId] = [
                                            'lesson' => $lesson->examParentLesson,
                                            'types' => ['exam'],
                                            'actions' => ['exam' => '/ajax/deleteExamParentLesson']
                                        ];
                                    }
                                }
                                if (!empty($parentLinks)):
                                ?>
                                    <dt class="col-sm-2">Bağlı Olduğu Ders</dt>
                                    <dd class="col-sm-4 p-0">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($parentLinks as $pl): ?>
                                                <li class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                                                    <a href="/admin/lesson/<?= $pl['lesson']->id ?>"
                                                        class="link-dark link-underline-opacity-0">
                                                        <?= $pl['lesson']->getFullName(addCode: true, addProgram: true) ?>
                                                    </a>
                                                    <?php foreach ($pl['types'] as $t): ?>
                                                        <form action="<?= $pl['actions'][$t] ?>" method="post"
                                                            class="d-inline ajaxDeleteParentLesson" title="<?= $t === 'exam' ? 'Sınav bağlantısını kaldır' : 'Ders bağlantısını kaldır' ?>">
                                                            <input type="hidden" name="id" value="<?= $lesson->id ?>">
                                                            <button type="submit"
                                                                class="badge <?= $t === 'exam' ? 'bg-info' : 'bg-primary' ?> border-0"
                                                                style="cursor:pointer;">
                                                                <?= $t === 'exam' ? 'Sınav' : 'Ders' ?>
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </dd>
                                <?php endif; ?>
                                <?php
                                // ── Bağlı Dersler (childLessons + examChildLessons) ──
                                $childLinks = [];
                                foreach ($lesson->childLessons ?? [] as $child) {
                                    $childLinks[$child->id] = ['lesson' => $child, 'types' => ['lesson'], 'actions' => ['lesson' => '/ajax/deleteParentLesson']];
                                }
                                foreach ($lesson->examChildLessons ?? [] as $examChild) {
                                    if (isset($childLinks[$examChild->id])) {
                                        // Hem ders hem sınav bağlantısı var
                                        $childLinks[$examChild->id]['types'][] = 'exam';
                                        $childLinks[$examChild->id]['actions']['exam'] = '/ajax/deleteExamParentLesson';
                                    } else {
                                        $childLinks[$examChild->id] = ['lesson' => $examChild, 'types' => ['exam'], 'actions' => ['exam' => '/ajax/deleteExamParentLesson']];
                                    }
                                }
                                if (!empty($childLinks)):
                                ?>
                                    <dt class="col-sm-2">Bağlı Dersler</dt>
                                    <dd class="col-sm-4 p-0">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($childLinks as $cl): ?>
                                                <li class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                                                    <a href="/admin/lesson/<?= $cl['lesson']->id ?>"
                                                        class="link-dark link-underline-opacity-0">
                                                        <?= $cl['lesson']->getFullName(addCode: true, addProgram: true) ?>
                                                    </a>
                                                    <?php foreach ($cl['types'] as $t): ?>
                                                        <form action="<?= $cl['actions'][$t] ?>" method="post"
                                                            class="d-inline ajaxDeleteParentLesson" title="<?= $t === 'exam' ? 'Sınav bağlantısını kaldır' : 'Ders bağlantısını kaldır' ?>">
                                                            <input type="hidden" name="id" value="<?= $cl['lesson']->id ?>">
                                                            <button type="submit"
                                                                class="badge <?= $t === 'exam' ? 'bg-info' : 'bg-primary' ?> border-0"
                                                                style="cursor:pointer;">
                                                                <?= $t === 'exam' ? 'Sınav' : 'Ders' ?>
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                        <div class="card-footer text-end">
                            <?php if (Gate::allowsRole("department_head")): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#CombineLessonModal">Ders Birleştir
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal"
                                    data-bs-target="#CombineExamLessonModal">Sınav Birleştir
                                </button>
                            <?php endif; ?>
                            <?php if (Gate::check("update", $lesson)): ?>
                                <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-primary">Dersi Düzenle</a>
                            <?php endif; ?>
                            <?php if (Gate::check("delete", $lesson)): ?>
                                <form action="/ajax/deletelesson/<?= $lesson->id ?>" class="ajaxFormDelete d-inline"
                                    method="post">
                                    <input type="hidden" name="id" value="<?= $lesson->id ?>">
                                    <input type="submit" class="btn btn-danger" value="Sil">
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <?php $user = $lesson->lecturer; ?>
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="profile-user-img img-fluid img-circle"
                                    src="<?= $user->getGravatarURL(150) ?>" alt="User profile picture">
                            </div>

                            <h3 class="profile-username text-center"><?= $user->getFullName() ?></h3>

                            <p class="text-muted text-center"><?= $user->title ?></p>

                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Ders</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= count($user->lessons ?? []) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Öğrenci sayısı</b>
                                    </div>
                                    <span
                                        class="badge text-bg-primary "><?= array_reduce($user->lessons ?? [], fn($sum, $l) => $sum + ($l->size ?? 0), 0) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Ders Saati</b>
                                    </div>
                                    <span
                                        class="badge text-bg-primary "><?= array_reduce($user->lessons ?? [], fn($sum, $l) => $sum + ($l->hours ?? 0), 0) ?></span>
                                </li>
                            </ul>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">
                            <a href="/admin/profile/<?= $user->id ?>" class="btn btn-primary">Profile git</a>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline card-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="lessonTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="schedule-tab" data-bs-toggle="pill" href="#schedule"
                                        role="tab" aria-controls="schedule" aria-selected="true">Ders Programı</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="exams-tab" data-bs-toggle="pill" href="#exams" role="tab"
                                        aria-controls="exams" aria-selected="false">Sınav Programı</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="lessonTabsContent">
                                <div class="tab-pane fade show active" id="schedule" role="tabpanel"
                                    aria-labelledby="schedule-tab">
                                    <?= $scheduleHTML ?>
                                </div>
                                <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                                    <!-- Nested Tabs for Exams -->
                                    <ul class="nav nav-tabs mb-3" id="examTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="midterm-tab" data-bs-toggle="tab"
                                                href="#midterm" role="tab">Ara Sınav</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="final-tab" data-bs-toggle="tab" href="#final"
                                                role="tab">Final</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="makeup-tab" data-bs-toggle="tab" href="#makeup"
                                                role="tab">Bütünleme</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="examTabsContent">
                                        <div class="tab-pane fade show active" id="midterm" role="tabpanel">
                                            <?= $midtermScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="final" role="tabpanel">
                                            <?= $finalScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="makeup" role="tabpanel">
                                            <?= $makeupScheduleHTML ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->

<div class="modal" tabindex="-1" id="CombineLessonModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/ajax/combineLesson" name="CombineLesson" id="CombineLesson" method="post"
                title="Dersler birleştiriliyor">
                <div class="modal-body">
                    <div class="accordion " id="accordionCombineLesson">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    Bu Dersi Başka Derse Bağla
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                                data-bs-parent="#accordionCombineLesson">
                                <div class="accordion-body">
                                    <p>
                                        Bu dersi başka bir derse bağladığınızda bu dersi programda düzenleyemezsiniz.Bu
                                        ders bağlandığı ders hangi gün ve saate eklenirse o saate otomatik olarak
                                        eklenir
                                    </p>
                                    <label for="parent_lesson_id" class="form-label">Birleştirilecek Ders</label>
                                    <select class="form-select" name="parent_lesson_id" id="parent_lesson_id">
                                        <option value="0">Ders Seçiniz</option>
                                        <?php
                                        $programName = "";
                                        /** @var Lesson $combineLesson */
                                        foreach ($combineLessonList as $combineLesson):
                                            if ($programName != ($combineLesson->program->name ?? '')) {
                                                $programName = $combineLesson->program->name ?? '';
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $combineLesson->id ?>"><?= $combineLesson->getFullName(addCode: true) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Bu Derse Başka Ders Bağla
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                                data-bs-parent="#accordionCombineLesson">
                                <div class="accordion-body">
                                    <p>
                                        Bu derse bağlanan dersler programda düzenlenemezler. Bağlı ders bağlandığı ders
                                        hangi gün ve saate eklenirse o saate otomatik olarak eklenir
                                    </p>
                                    <input type="hidden" name="lesson_id" id="parent_lesson_id"
                                        value="<?= $lesson->id ?>">
                                    <label for="child_lesson_id" class="form-label">Birleştirilecek Ders</label>
                                    <select class="form-select" name="child_lesson_id" id="child_lesson_id">
                                        <option value="0">Ders Seçiniz</option>
                                        <?php
                                        $programName = "";
                                        /** @var Lesson $combineLesson */
                                        foreach ($combineLessonList as $combineLesson):
                                            if ($programName != ($combineLesson->program->name ?? '')) {
                                                $programName = $combineLesson->program->name ?? '';
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $combineLesson->id ?>"><?= $combineLesson->getFullName(addCode: true) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="modalCancel" data-bs-dismiss="modal">Kapat
                    </button>
                    <button type="submit" class="btn btn-primary" id="modalConfirm">Birleştir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Saat Farkı Seçim Modalı -->
<div class="modal" tabindex="-1" id="CombineLessonConfirmModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ders Saati Seçimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" id="hoursDiffAlert"></div>
                <p class="mb-2 fw-semibold">Aşağıdaki dersi hangi saaten <strong>kaldırılacak?</strong></p>
                <p class="text-muted small">Bağlanacak ders için program oluşturulurken seçtiğiniz saatler <strong>kopyalanmaz</strong>.</p>
                <div id="itemSelectionList" class="list-group mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="confirmCombineBtn" disabled>Birleştir</button>
            </div>
        </div>
    </div>
</div>

<!-- Sınav Birleştirme Modalı -->
<div class="modal" tabindex="-1" id="CombineExamLessonModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/ajax/combineExamLesson" name="CombineExamLesson" id="CombineExamLesson" method="post"
                title="Sınav birleştiriliyor">
                <div class="modal-header">
                    <h5 class="modal-title">Sınav Birleştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="accordionCombineExamLesson">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingExamOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseExamOne" aria-expanded="false" aria-controls="collapseExamOne">
                                    Bu Dersi Başka Derse Bağla
                                </button>
                            </h2>
                            <div id="collapseExamOne" class="accordion-collapse collapse" aria-labelledby="headingExamOne"
                                data-bs-parent="#accordionCombineExamLesson">
                                <div class="accordion-body">
                                    <p>
                                        Bu dersi başka bir derse bağladığınızda bu dersin sınav programını düzenleyemezsiniz. Bu ders sınav programında bağlandığı ders ile aynı saate otomatik olarak eklenir.
                                    </p>
                                    <label for="exam_parent_lesson_select" class="form-label">Birleştirilecek Ders</label>
                                    <select class="tom-select" name="parent_lesson_id" id="exam_parent_lesson_select"
                                        placeholder="Ders ara...">
                                        <option value="0">Ders Seçiniz</option>
                                        <?php
                                        $programName = "";
                                        /** @var Lesson $examCombineLesson */
                                        foreach ($examCombineLessonList as $examCombineLesson):
                                            // Zaten sınav birleştirilmiş dersleri atla
                                            if ($examCombineLesson->exam_parent_lesson_id) continue;
                                            // Kendisini atla
                                            if ($examCombineLesson->id === $lesson->id) continue;
                                            if ($programName != ($examCombineLesson->program->name ?? '')) {
                                                $programName = $examCombineLesson->program->name ?? '';
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $examCombineLesson->id ?>"><?= $examCombineLesson->getFullName(addCode: true, addSize: true,addProgram: true) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingExamTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseExamTwo" aria-expanded="false" aria-controls="collapseExamTwo">
                                    Bu Derse Başka Ders Bağla
                                </button>
                            </h2>
                            <div id="collapseExamTwo" class="accordion-collapse collapse" aria-labelledby="headingExamTwo"
                                data-bs-parent="#accordionCombineExamLesson">
                                <div class="accordion-body">
                                    <p>
                                        Bu derse bağlanan derslerin sınav programı düzenlenemez. Bağlı dersler sınav programında bu ders ile aynı saate otomatik olarak eklenir.
                                    </p>
                                    <input type="hidden" name="lesson_id" value="<?= $lesson->id ?>">
                                    <label for="exam_child_lesson_select2" class="form-label">Birleştirilecek Ders</label>
                                    <select class="tom-select" name="child_lesson_id" id="exam_child_lesson_select2"
                                        placeholder="Ders ara...">
                                        <option value="0">Ders Seçiniz</option>
                                        <?php
                                        $programName = "";
                                        foreach ($examCombineLessonList as $examCombineLesson):
                                            if ($examCombineLesson->exam_parent_lesson_id) continue;
                                            if ($examCombineLesson->id === $lesson->id) continue;
                                            if ($programName != ($examCombineLesson->program->name ?? '')) {
                                                $programName = $examCombineLesson->program->name ?? '';
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $examCombineLesson->id ?>"><?= $examCombineLesson->getFullName(addCode: true, addSize: true,addProgram: true) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-info">Birleştir</button>
                </div>
            </form>
        </div>
    </div>
</div>
