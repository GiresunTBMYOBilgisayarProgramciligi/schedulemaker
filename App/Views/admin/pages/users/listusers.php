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
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
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
                                <!--<th>İd</th>-->
                                <th>Ünvanı Adı Soyadı</th>
                                <th>e-Posta</th>
                                <th>Bölüm</th>
                                <th>Program</th>
                                <th>Yetki</th>
                                <!--<th>Kayıt Tarihi</th>-->
                                <th>Son Giriş Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($userController->getUsersList() as $user): ?>
                                <tr>
                                    <!--<td><?php /*= $user->id */?></td>-->
                                    <td><?= $user->getFullName() ?></td>
                                    <td><?= $user->mail ?></td>
                                    <td><?= $user->getDepartmentName() ?></td>
                                    <td><?= $user->getProgramName() ?></td>
                                    <td><?= $user->getRoleName() ?></td>
                                    <!--<td><?php /*= $user->getRegisterDate() */?></td>-->
                                    <td><?= $user->getLastLogin() ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-primary">İşlemler</button>
                                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                                <span class="sr-only">İşlemler listesi</span>
                                            </button>
                                            <div class="dropdown-menu" role="menu" style="">
                                                <a class="dropdown-item" href="/admin/profile/<?=$user->id?>">Gör</a>
                                                <a class="dropdown-item" href="/admin/edituser/<?=$user->id?>">Düzenle</a>
                                                <div class="dropdown-divider"></div>
                                                <form action="/ajax/deleteuser/<?=$user->id?>" class="ajaxFormDelete" id="deleteUser-<?=$user->id?>" method="post">
                                                    <input type="hidden" name="id" value="<?=$user->id?>">
                                                    <input type="submit" class="dropdown-item" value="Sil">
                                                </form>
                                            </div>
                                        </div>
                                    </td>
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
