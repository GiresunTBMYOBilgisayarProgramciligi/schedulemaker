<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\Program $program Bölüm listesinde döngüde kullanılan program değişkeni
 * @var \App\Controllers\DepartmentController $departmentController
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Controllers\ProgramController $programController
 * @var string $scheduleHTML
 * @var \App\Models\User $currentUser
 * @var array $programs
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
                <div class="col-sm-6"><h3 class="mb-0">Başlangıç</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Ana Sayfa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Başlangıç</li>
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
            <!-- Small boxes (Stat box) -->
            <div class="row">
                <div class="col">
                    <!-- small box -->
                    <div class="small-box text-bg-primary">
                        <div class="inner">
                            <h3><?= $userController->getAcademicCount() ?></h3>

                            <p>Öğretim Elemanı</p>
                        </div>
                        <div class="small-box-icon">
                            <i class="bi bi-person-video3"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col">
                    <!-- small box -->
                    <div class="small-box text-bg-success">
                        <div class="inner">
                            <h3><?= $classroomController->getCount() ?></h3>

                            <p>Derslik</p>
                        </div>
                        <div class="small-box-icon">
                            <i class="bi bi-door-open"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col">
                    <!-- small box -->
                    <div class="small-box text-bg-warning">
                        <div class="inner">
                            <h3><?= $lessonController->getCount() ?></h3>

                            <p>Ders</p>
                        </div>
                        <div class="small-box-icon">
                            <i class="bi bi-book"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col">
                    <!-- small box -->
                    <div class="small-box text-bg-danger">
                        <div class="inner">
                            <h3><?= $departmentController->getCount() ?></h3>

                            <p>Bölüm</p>
                        </div>
                        <div class="small-box-icon">
                            <i class="bi bi-buildings"></i>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <!-- small box -->
                    <div class="small-box text-bg-info">
                        <div class="inner">
                            <h3><?= $programController->getCount() ?></h3>

                            <p>Program</p>
                        </div>
                        <div class="small-box-icon">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
            </div>
            <!-- /.row -->
            <?php if (\App\Helpers\isAuthorized("manager", true)): ?>
                <h4><?= $currentUser->getProgramName()?> Ders Programı</h4>
                <?= $scheduleHTML ?>
            <?php else: ?>
                <h4>Programlar</h4>
                <!-- Main row -->
                <div class="row">
                    <?php foreach ($programs as $program): ?>
                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 d-flex align-items-stretch flex-column">
                            <div class="card d-flex flex-fill mb-3">
                                <div class="card-header text-muted border-bottom-0">
                                    <?= $program->getDepartment()->name ?>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12">
                                            <h2 class="lead"><b><?= $program->name ?></b></h2>

                                            <ul class="list-group list-group-flush text-muted mt-2">
                                                <li class="list-group-item small">
                                                <span class="">
                                                    <i class="bi bi-mortarboard"></i>
                                                </span>
                                                    <strong>Bölüm Başkan:</strong>
                                                    <a href="/admin/profile/<?= $program->getDepartment()->getChairperson()->id ?>">
                                                        <?= $program->getDepartment()->getChairperson()->getFullName() ?>
                                                    </a>
                                                </li>
                                                <li class="list-group-item small">
                                                <span class="">
                                                    <i class="bi bi-person-vcard"></i>
                                                </span>
                                                    <strong>Akademisyen Sayısı:</strong>
                                                    <?= $program->getLecturerCount() ?>
                                                </li>
                                                <li class="list-group-item small">
                                                <span class="">
                                                    <i class="bi bi-book"></i>
                                                </span>
                                                    <strong>Ders Sayısı:</strong>
                                                    <?= $program->getLessonCount() ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="text-end">
                                        <a href="/admin/department/<?= $program->getDepartment()->id ?>"
                                           class="btn btn-sm btn-primary">
                                            Bölüm Detayları
                                        </a>
                                        <a href="/admin/program/<?= $program->id ?>"
                                           class="btn btn-sm btn-primary">
                                            Detaylar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- /.row (main row) -->
            <?php endif; ?>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->