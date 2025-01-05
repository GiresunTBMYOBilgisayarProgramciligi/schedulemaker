<?php
/**
 * @var array $programs \App\Models\Program->getPrograms())
 * @var \App\Models\Program $program
 * @var array $departments \App\Models\Department->getDepartments())
 * @var \App\Models\Department $department
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $lecturer
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
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Düzenle</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content ">
        <div class="card card-solid">
            <form action="/ajax/updateLesson" method="post" class="ajaxForm" title="Ders Düzenle">
                <input type="hidden" name="id" value="<?= $lesson->id ?>">
                <div class="card-body pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="code">Kodu</label>
                                        <input type="text" class="form-control" id="code" name="code"
                                               placeholder="Kodu"
                                               value="<?= $lesson->code ?>"
                                        required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Adı</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               placeholder="Adı" value="<?= $lesson->name ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password">Dersin Hocası</label>
                                        <select class="form-control" id="lecturer_id" name="lecturer_id">
                                            <?php foreach ($userController->getLecturerList() as $lecturer): ?>
                                                <option value="<?= $lecturer->id ?>"
                                                    <?= $lecturer->id == $lesson->lecturer_id ? "selected" : "" ?>><?= $lecturer->getFullName() ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="size">Mevcut</label>
                                        <input type="number" class="form-control" id="size" name="size"
                                               placeholder="Mevcut" value="<?=$lesson->size?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hours">Ders Saati</label>
                                        <input type="number" class="form-control" id="hours" name="hours"
                                               placeholder="Ders Saati" value="<?=$lesson->hours?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department_id">Bölüm</label>
                                        <select class="form-control" id="department_id" name="department_id">
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= $department->id ?>"
                                                    <?= $department->id == $lesson->department_id ? "selected" : "" ?>><?= $department->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="program_id">Program</label>
                                        <select class="form-control" id="program_id" name="program_id">
                                            <?php foreach ($programs as $program): var_dump($programs); ?>
                                                <option value="<?= $program->id ?>"
                                                    <?= $program->id == $lesson->program_id ? "selected" : "" ?>><?= $program->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
