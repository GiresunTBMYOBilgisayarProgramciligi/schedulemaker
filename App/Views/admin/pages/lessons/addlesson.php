<?php
/**
 * @var array $programs \App\Models\Program->getPrograms())
 * @var \App\Models\Program $program
 * @var array $departments \App\Models\Department->getDepartments())
 * @var \App\Models\Department $department
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $lecturer
 * @var \App\Controllers\LessonController $lessonController
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
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Ekle</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content ">
        <div class="card card-solid">
            <form action="/ajax/addLesson" method="post" class="ajaxForm" title="Yeni Ders Ekle">
                <div class="card-body pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="code">Kodu</label>
                                        <input type="text" class="form-control" id="code" name="code"
                                               placeholder="Kodu"
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="type">Türü</label>
                                        <select class="form-control" id="type" name="type">
                                            <?php foreach ($lessonController->getTypeList() as $type): ?>
                                                <option value="<?= $type ?>"><?= $type ?></option>
                                            <?php endforeach ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="season">Dönemi</label>
                                        <select class="form-control" id="season" name="season">
                                            <?php foreach ($lessonController->getSeasonList() as $season): ?>
                                                <option value="<?= $season ?>"><?= $season ?></option>
                                            <?php endforeach ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Adı</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               placeholder="Adı" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lecturer_id">Dersin Hocası</label>
                                        <select class="form-control" id="lecturer_id" name="lecturer_id">
                                            <?php foreach ($userController->getLecturerList() as $lecturer): ?>
                                                <option value="<?= $lecturer->id ?>"><?= $lecturer->getFullName() ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="size">Mevcut</label>
                                        <input type="number" class="form-control" id="size" name="size"
                                               placeholder="Mevcut" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hours">Ders Saati</label>
                                        <input type="number" class="form-control" id="hours" name="hours"
                                               placeholder="Ders Saati" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department_id">Bölüm</label>
                                        <select class="form-control" id="department_id" name="department_id">
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= $department->id ?>"><?= $department->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="program_id">Program</label>
                                        <select class="form-control" id="program_id" name="program_id">
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?= $program->id ?>"><?= $program->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
