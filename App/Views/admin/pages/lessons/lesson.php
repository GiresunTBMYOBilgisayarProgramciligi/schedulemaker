<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var string $page_title
 */
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
                        li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
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
                <div class="col-12">
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
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->type, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Saat</dt>
                                <dd class="col-sm-8"><?= $lesson->hours ?></dd>
                                <dt class="col-sm-4">Dönemi</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($lesson->semester_no, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Hocası</dt>
                                <dd class="col-sm-8"><a href="/admin/profile/<?= $lesson->getLecturer()->id ?>">
                                        <?= htmlspecialchars($lesson->getLecturer()->getFullName(), ENT_QUOTES, 'UTF-8') ?></a>
                                </dd>
                            </dl>
                        </div>
                        <div class="card-footer text-end">
                            <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-primary">Dersi Düzenle</a>
                            <form action="/ajax/deletelesson/<?= $lesson->id ?>" class="ajaxFormDelete d-inline"
                                  method="post">
                                <input type="hidden" name="id" value="<?= $lesson->id ?>">
                                <input type="submit" class="btn btn-danger" value="Sil">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Program</h3>
                            <!-- todo Bir ders birden fazla bölümde varsa ders programı için bölüm seçme listesi olmalı -->
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                    <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?= $scheduleController->createScheduleTable(["owner_type" => "lesson", "owner_id" => $lesson->id]) ?>
                        </div>
                        <div class="card-footer">

                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->