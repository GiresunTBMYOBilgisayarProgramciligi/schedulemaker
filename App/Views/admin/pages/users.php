<?php
/**
  * @var \App\Models\UsersController $usersController
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var array $programs \App\Models\Program->getPrograms())
 * @var array $departments \App\Models\Department->getDepartments())
 */
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Main content -->
    <section class="content p-0">
        <!-- Main row -->
        <div class="row">
            <div class="col-12 ">
                <div class="card card-primary card-outline card-outline-tabs">
                    <div class="card-header p-0 border-bottom-0">
                        <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link bg-primary active" id="user-action-list-tab" data-toggle="pill"
                                   href="#custom-tabs-four-home" role="tab" aria-controls="custom-tabs-four-home"
                                   aria-selected="true">Liste</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="user-action-add-tab" data-toggle="pill"
                                   href="#user-action-add-tabContent" role="tab"
                                   aria-controls="custom-tabs-four-profile" aria-selected="false">Ekle</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="user-action-list-tabContent">
                            <div class="tab-pane fade show active" id="custom-tabs-four-home" role="tabpanel"
                                 aria-labelledby="user-action-list-tab">
                                <div class="row">
                                    <?php foreach ($usersController->get_users_list() as $user): ?>
                                        <div class="col-12 col-sm-6 col-md-4 d-flex align-items-stretch flex-column">
                                            <div class="card bg-light d-flex flex-fill">
                                                <div class="card-header text-muted border-bottom-0">
                                                    <?= $user->title ?>
                                                </div>
                                                <div class="card-body pt-0">
                                                    <div class="row">
                                                        <div class="col-7">
                                                            <h2 class="lead"><b><?= $user->getFullName() ?></b></h2>
                                                            <br>
                                                            <p class="text-sm">
                                                                <b>Bölüm: </b>
                                                                <?= $user->getDepartmentName() ?>

                                                                <b>Program: </b>
                                                                <?= $user->getProgramName() ?>
                                                            </p>
                                                            <ul class="ml-4 mb-0 fa-ul text-muted">
                                                                <li class="small"><span class="fa-li"><i
                                                                                class="fas fa-lg fa-user-tag"></i></span>
                                                                    Rol: <?= $user->getRoleName() ?></li>
                                                                <li class="small"><span class="fa-li"><i
                                                                                class="fas fa-lg fa-business-time"></i></span>
                                                                    Son
                                                                    Giriş: <?= $user->last_login->format('Y-m-d H:i:s') ?>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="col-5 text-center">
                                                            <img src="<?= $user->getGravatarURL(100) ?>"
                                                                 alt="user-avatar" class="img-circle img-fluid">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="text-right">
                                                        <a href="/admin/profile/<?= $user->id ?>"
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-user"></i> Profil
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="user-action-add-tabContent" role="tabpanel"
                                 aria-labelledby="custom-tabs-four-profile-tab">
                                <form action="/ajax/addNewUser" method="post" class="ajaxForm" title="Yeni Kullanıcı Ekle">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name">Adı</label>
                                                <input type="text" class="form-control" id="name" name="name" placeholder="Adı" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name">Soyadı</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Soyadı" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="mail">e-Posta</label>
                                                <input type="email" class="form-control" id="mail" name="mail" placeholder="e-Posta" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password">Şifre</label>
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Şifre" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="role">Rol</label>
                                                <select class="form-control" id="role" name="role">
                                                    <option value="user" selected>Kullanıcı</option>
                                                    <option value="lecturer">Akademisyen</option>
                                                    <option value="admin">Yönetici</option>
                                                    <option value="department_head">Bölüm Başkanı</option>
                                                    <option value="manager">Müdür</option>
                                                    <option value="submanager">Müdür Yardımcısı</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="title">Ünvan</label>
                                                <select class="form-control" id="title" name="title">
                                                    <option value="Öğr. Gör." selected>Öğr. Gör.</option>
                                                    <option value="Öğr. Gör. Dr.">Öğr. Gör. Dr.</option>
                                                    <option value="Dr. Öğretim Üyesi">Dr. Öğretim Üyesi</option>
                                                    <option value="Doç. Dr. ">Doç. Dr.</option>
                                                    <option value="Prof. Dr.">Prof. Dr.</option>
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
                                                        <option value="<?= $department['id'] ?>"><?= $department['name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="program_id">Program</label>
                                                <select class="form-control" id="program_id" name="program_id">
                                                    <?php foreach ($programs as $program): var_dump($programs);?>
                                                        <option value="<?= $program['id'] ?>"><?= $program['name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Ekle</button>
                                </form>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- /.row (main row) -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
