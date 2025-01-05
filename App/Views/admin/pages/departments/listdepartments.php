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
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Bölüm İşlemleri</li>
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
                                <th>Adı</th>
                                <th>Bölüm Başkanı</th>
                                <th>İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($departmentController->getDepartmentsList() as $department): ?>
                                <tr>
                                    <td><?= $department->id ?></td>
                                    <td><?= $department->name ?></td>
                                    <td><?= $department->getChairperson()->getFullName() ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-primary">İşlemler</button>
                                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                                <span class="sr-only">İşlemler listesi</span>
                                            </button>
                                            <div class="dropdown-menu" role="menu" style="">
                                                <a class="dropdown-item" href="/admin/department/<?=$department->id?>">Gör</a>
                                                <a class="dropdown-item" href="/admin/editdepartment/<?=$department->id?>">Düzenle</a>
                                                <div class="dropdown-divider"></div>
                                                <form action="/ajax/deletedepartment/<?=$department->id?>" class="ajaxFormDelete" id="deleteProgram-<?=$department->id?>" method="post">
                                                    <input type="hidden" name="id" value="<?=$department->id?>">
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
