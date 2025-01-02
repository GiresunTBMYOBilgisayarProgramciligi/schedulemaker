<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var array $programs \App\Models\Program->getPrograms())
 * @var array $departments \App\Models\Department->getDepartments())
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 */
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= $page_title ?></h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Liste</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content ">
        <div class="card card-solid">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <table id="user-list-table" class="table table-bordered table-striped dataTable dtr-inline">
                            <thead>
                            <tr>
                                <th>İd</th>
                                <th>Kodu</th>
                                <th>Adı</th>
                                <th>Mevcudu</th>
                                <th>Saati</th>
                                <th>Hocası</th>
                                <th>Bölüm</th>
                                <th>Program</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lessonController->getLessonsList() as $lesson): ?>
                                <tr class="odd">
                                    <td><?= $lesson->id ?></td>
                                    <td><?= $lesson->code ?></td>
                                    <td><?= $lesson->name ?></td>
                                    <td><?= $lesson->size ?></td>
                                    <td><?= $lesson->hours ?></td>
                                    <td><?= $lesson->getLecturer()->getFullName() ?></td>
                                    <td><?= $lesson->getDepartment()->name ?></td>
                                    <td><?= $lesson->getProgam()->name ?></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
