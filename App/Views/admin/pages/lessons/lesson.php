<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var Lesson $lesson
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var string $page_title
 * @var string $scheduleHTML
 * @var array $combineLessonList
 */

use App\Models\Lesson;
use function App\Helpers\isAuthorized;

?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
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
                                <dt class="col-sm-2">Ders Kodu</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->code, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Ders Adı</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Ders Türü</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->getTypeName(), ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Saat</dt>
                                <dd class="col-sm-4"><?= $lesson->hours ?></dd>
                                <dt class="col-sm-2">Dönemi</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->semester_no . ". Yarıyıl", ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Bölüm</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->getDepartment()->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Program</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->getProgram()->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Derslik Türü</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->getClassroomTypeName(), ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Akademik yıl ve Dönem</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->academic_year . " " . $lesson->semester, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-2">Mevcudu</dt>
                                <dd class="col-sm-4"><?= htmlspecialchars($lesson->size, ENT_QUOTES, 'UTF-8') ?></dd>
                                <?php
                                if ($lesson->getParentLesson()):
                                    ?>
                                    <dt class="col-sm-2">Bağlı Olduğu Ders</dt>
                                    <dd class="col-sm-10 p-0">
                                        <a class="link-dark link-underline-opacity-0"
                                           href="/admin/lesson/<?= $lesson->getParentLesson()->id ?>">
                                            <?= htmlspecialchars($lesson->getParentLesson()->getFullName() . "-" . $lesson->getProgram()->name, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </dd>
                                <?php endif; ?>
                                <?php
                                if (count($lesson->getChildLessonList()) > 0):
                                    ?>
                                    <dt class="col-sm-2">Bağlı Dersler</dt>
                                    <dd class="col-sm-10 p-0">
                                        <ul class="list-group list-group-flush">
                                            <?php
                                            foreach ($lesson->getChildLessonList() as $childLesson) {
                                                echo '<li class="list-group-item"><a href="/admin/lesson/' . $childLesson->id . '" class="link-dark link-underline-opacity-0">';
                                                echo htmlspecialchars($childLesson->getFullName() . "-" . $childLesson->getProgram()->name, ENT_QUOTES, 'UTF-8') . "<br>";
                                                echo '</a></li>';
                                            }
                                            ?>
                                        </ul>

                                    </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                        <div class="card-footer text-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#CombineLessonModal">Ders Birleştir
                            </button>
                            <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-primary">Dersi Düzenle</a>
                            <?php if (isAuthorized("department_head")): ?>
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
                    <?php $user = $lesson->getLecturer() ?>
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
                                    <span class="badge text-bg-primary "><?= $user->getLessonCount() ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Öğrenci sayısı</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= $user->getTotalStudentCount() ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Ders Saati</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= $user->getTotalLessonHours() ?></span>
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
            <?= $scheduleHTML ?>
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
                  class="ajaxFormCombineLesson" title="Dersler birleştiriliyor">
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
                                            if ($programName != $combineLesson->getProgram()->name) {
                                                $programName = $combineLesson->getProgram()->name;
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $combineLesson->id ?>"><?= $combineLesson->getFullName() ?></option>
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
                                            if ($programName != $combineLesson->getProgram()->name) {
                                                $programName = $combineLesson->getProgram()->name;
                                                echo '<option disabled>' . $programName . '</option>';
                                            }
                                            ?>
                                            <option value="<?= $combineLesson->id ?>"><?= $combineLesson->getFullName() ?></option>
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