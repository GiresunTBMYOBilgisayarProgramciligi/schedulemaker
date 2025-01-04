<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var array $programs \App\Models\Program->getPrograms())
 * @var array $departments \App\Models\Department->getDepartments())
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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
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
            <form action="/ajax/addNewUser" method="post" class="ajaxForm" title="Yeni Kullanıcı Ekle">
                <div class="card-body pb-0">
                    <div class="row">
                        <div class="col-md-12">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Adı</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               placeholder="Adı"
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Soyadı</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                               placeholder="Soyadı" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="mail">e-Posta</label>
                                        <input type="email" class="form-control" id="mail" name="mail"
                                               placeholder="e-Posta" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password">Şifre</label>
                                        <input type="password" class="form-control" id="password" name="password"
                                               placeholder="Şifre" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="role">Rol</label>
                                        <select class="form-control" id="role" name="role">
                                            <?php foreach ($userController->getRoleList() as $role => $value): ?>
                                                <option value="<?= $role ?>"><?= $value ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title">Ünvan</label>
                                        <select class="form-control" id="title" name="title">
                                            <?php foreach ($userController->getTitleList() as $title): ?>
                                                <option value="<?= $title ?>"><?= $title ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                                    <?= $department->id == $user->department_id ? "selected" : "" ?>><?= $department->name ?></option>
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
                                                    <?= $program->id == $user->program_id ? "selected" : "" ?>><?= $program->name ?></option>
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
