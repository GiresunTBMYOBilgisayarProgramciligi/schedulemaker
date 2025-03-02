<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var string $page_title
 * @var string $scheduleHTML
 */

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
                                <dt class="col-sm-4">Ders Kodu</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->code, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Ders Adı</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Ders Türü</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->getTypeName(), ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Saat</dt>
                                <dd class="col-sm-8"><?= $lesson->hours ?></dd>
                                <dt class="col-sm-4">Dönemi</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->semester_no, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Bölüm</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->getDepartment()->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Program</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->getProgram()->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Derslik Türü</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->getClassroomTypeName(), ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Akademik yıl ve Dönem</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->academic_year." ". $lesson->semester, ENT_QUOTES, 'UTF-8') ?></dd>
                            </dl>
                        </div>
                        <div class="card-footer text-end">
                            <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-primary">Dersi Düzenle</a>
                            <?php if (isAuthorized("department_head")):?>
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
                    <?php $user = $lesson->getLecturer()?>
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