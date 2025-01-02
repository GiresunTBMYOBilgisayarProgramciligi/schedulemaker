<?php
/**
 * @var \App\Controllers\UserController $usersController
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
                                <th>Ünvanı Adı Soyadı</th>
                                <th>e-Posta</th>
                                <th>Bölüm</th>
                                <th>Program</th>
                                <th>Yetki</th>
                                <th>Kayıt Tarihi</th>
                                <th>Son Giriş Tarihi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($usersController->getUsersList() as $user): ?>
                                <tr class="odd">
                                    <td><?= $user->id ?></td>
                                    <td><?= $user->getFullName() ?></td>
                                    <td><?= $user->mail ?></td>
                                    <td><?= $user->getDepartmentName() ?></td>
                                    <td><?= $user->getProgramName() ?></td>
                                    <td><?= $user->getRoleName() ?></td>
                                    <td><?= $user->getRegisterDate() ?></td>
                                    <td><?= $user->getLastLogin() ?></td>
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
