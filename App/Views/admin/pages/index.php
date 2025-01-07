<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\Program $program Bölüm listesinde döngüde kullanılan program değişkeni
 * @var \App\Controllers\DepartmentController $departmentController
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Controllers\ProgramController $programController
 */
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Başlangıç</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item active">Başlangıç</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Small boxes (Stat box) -->
            <div class="row justify-content-center">
                <div class="col-lg-2 col-6">
                    <!-- small box -->
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $userController->getAcademicCount() ?></h3>

                            <p>Öğretim Elemanı</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-2 col-6">
                    <!-- small box -->
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $classroomController->getCount() ?></h3>

                            <p>Derslik</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-2 col-6">
                    <!-- small box -->
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $lessonController->getCount() ?></h3>

                            <p>Ders</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-2 col-6">
                    <!-- small box -->
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $departmentController->getCount() ?></h3>

                            <p>Bölüm</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-school"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <!-- small box -->
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $programController->getCount() ?></h3>

                            <p>Program</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
            </div>
            <!-- /.row -->
            <h4>Programlar</h4>
            <!-- Main row -->
            <div class="row">
                <?php foreach ($programController->getProgramsList() as $program): ?>
                    <div class="col-12 col-sm-6 col-md-6 col-lg-4 d-flex align-items-stretch flex-column">
                        <div class="card  d-flex flex-fill">
                            <div class="card-header text-muted border-bottom-0">
                                <?= $program->getDepartment()->name ?>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-12">
                                        <h2 class="lead"><b><?= $program->name ?></b></h2>
                                        <br>

                                        <ul class="ml-4 mb-0 fa-ul text-muted">
                                            <li class="small">
                                                <span class="fa-li">
                                                    <i class="fas fa-lg fa-user-graduate"></i>
                                                </span>
                                                <strong>Bölüm Başkan:</strong>
                                                <a href="/admin/profile/<?= $program->getDepartment()->getChairperson()->id ?>">
                                                    <?= $program->getDepartment()->getChairperson()->getFullName() ?>
                                                </a>
                                            </li>
                                            <li class="small">
                                                <span class="fa-li">
                                                    <i class="fas fa-lg fa-user"></i>
                                                </span>
                                                <strong>Akademisyen Sayısı:</strong>
                                                <?= $program->getLecturerCount() ?>
                                            </li>
                                            <li class="small">
                                                <span class="fa-li">
                                                    <i class="fas fa-lg fa-book-open"></i>
                                                </span>
                                                <strong>Ders Sayısı:</strong>
                                                <?= $program->getLessonCount() ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="text-right">
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
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->