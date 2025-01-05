<?php
/**
 * @var \App\Controllers\DepartmentController $departmentController
 * @var \App\Models\Department $department
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
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Bölüm İşlemleri</li>
                        <li class="breadcrumb-item active">Bölüm</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Bölüm Bilgileri -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Bölüm Bilgileri</h3>

                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <p><strong>Bölüm Adı:</strong> <?= $department->name ?></p>
                    <p><strong>Açıklama:</strong> <?= $department->description ?></p>
                    <p><strong>Program Sayısı:</strong> <?= $department->getProgramCount() ?></p>
                </div>
                <!-- /.card-body -->
                <div class="card-footer">
                    <a href="/admin/editdepartment/<?= $department->id ?>" class="btn btn-primary">Bölümü Düzenle</a>
                    <a href="/admin/addprogram/<?= $department->id ?>" class="btn btn-success">Program Ekle</a>
                    <form action="/ajax/deletedepartment/<?=$department->id?>" class="ajaxFormDelete d-inline" id="deleteProgram-<?=$department->id?>" method="post">
                        <input type="hidden" name="id" value="<?=$department->id?>">
                        <input type="submit" class="btn btn-danger" value="Sil" role="button">
                    </form>
                </div>
                <!-- /.card-footer -->
            </div>

            <!-- İlişkili Programlar -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">İlişkili Programlar</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="user-list-table" class="table table-bordered table-striped dataTable dtr-inline">
                        <thead>
                        <tr>
                            <th>İd</th>
                            <th>Adı</th>
                            <th>Bölüm</th>
                            <th>İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($department->getPrograms() as $program): ?>
                            <tr>
                                <td><?= $program->id ?></td>
                                <td><?= $program->name ?></td>
                                <td><?= $program->getDepartment()->name ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary">İşlemler</button>
                                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                            <span class="sr-only">İşlemler listesi</span>
                                        </button>
                                        <div class="dropdown-menu" role="menu" style="">
                                            <a class="dropdown-item" href="/admin/program/<?=$program->id?>">Gör</a>
                                            <a class="dropdown-item" href="/admin/editprogram/<?=$program->id?>">Düzenle</a>
                                            <div class="dropdown-divider"></div>
                                            <form action="/ajax/deleteprogram/<?=$program->id?>" class="ajaxFormDelete" id="deleteProgram-<?=$program->id?>" method="post">
                                                <input type="hidden" name="id" value="<?=$program->id?>">
                                                <input type="submit" class="dropdown-item" value="Sil">
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">İlişkili Akademisyenler</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
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
                        <?php foreach ($department->getLecturers() as $lecturer): ?>
                            <tr>
                                <!--<td><?php /*= $lecturer->id */?></td>-->
                                <td><?= $lecturer->getFullName() ?></td>
                                <td><?= $lecturer->mail ?></td>
                                <td><?= $lecturer->getDepartmentName() ?></td>
                                <td><?= $lecturer->getProgramName() ?></td>
                                <td><?= $lecturer->getRoleName() ?></td>
                                <!--<td><?php /*= $lecturer->getRegisterDate() */?></td>-->
                                <td><?= $lecturer->getLastLogin() ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary">İşlemler</button>
                                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                            <span class="sr-only">İşlemler listesi</span>
                                        </button>
                                        <div class="dropdown-menu" role="menu" style="">
                                            <a class="dropdown-item" href="/admin/profile/<?=$lecturer->id?>">Gör</a>
                                            <a class="dropdown-item" href="/admin/edituser/<?=$lecturer->id?>">Düzenle</a>
                                            <div class="dropdown-divider"></div>
                                            <form action="/ajax/deleteuser/<?=$lecturer->id?>" class="ajaxFormDelete" id="deleteUser-<?=$lecturer->id?>" method="post">
                                                <input type="hidden" name="id" value="<?=$lecturer->id?>">
                                                <input type="submit" class="dropdown-item" value="Sil">
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">İlişkili Dersler</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="user-list-table" class="table table-bordered table-striped dataTable dtr-inline">
                        <thead>
                        <tr>
                            <th>İd</th>
                            <th>Kodu</th>
                            <th>Adı</th>
                            <th>Türü</th>
                            <th>Mevcudu</th>
                            <th>Saati</th>
                            <th>Dönemi</th>
                            <th>Hocası</th>
                            <th>Bölüm</th>
                            <th>Program</th>
                            <th>İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($department->getLessons as $lesson): ?>
                            <tr>
                                <td><?= $lesson->id ?></td>
                                <td><?= $lesson->code ?></td>
                                <td><?= $lesson->name ?></td>
                                <td><?= $lesson->type ?></td>
                                <td><?= $lesson->size ?></td>
                                <td><?= $lesson->hours ?></td>
                                <td><?= $lesson->season ?></td>
                                <td><?= $lesson->getLecturer()->getFullName() ?></td>
                                <td><?= $lesson->getDepartment()->name ?></td>
                                <td><?= $lesson->getProgam()->name ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary">İşlemler</button>
                                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                            <span class="sr-only">İşlemler listesi</span>
                                        </button>
                                        <div class="dropdown-menu" role="menu" style="">
                                            <a class="dropdown-item" href="/admin/lesson/<?=$lesson->id?>">Gör</a>
                                            <a class="dropdown-item" href="/admin/editlesson/<?=$lesson->id?>">Düzenle</a>
                                            <div class="dropdown-divider"></div>
                                            <form action="/ajax/deletelesson/<?=$lesson->id?>" class="ajaxFormDelete" id="deleteProgram-<?=$lesson->id?>" method="post">
                                                <input type="hidden" name="id" value="<?=$lesson->id?>">
                                                <input type="submit" class="dropdown-item" value="Sil">
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
        </div>
    </section>
</div>
