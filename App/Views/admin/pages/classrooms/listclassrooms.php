<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Models\Classroom $classroom
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
                        <li class="breadcrumb-item">Derslik İşlemleri</li>
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
                                <th>Ders Mevcudu</th>
                                <th>Sınav Mevcudu</th>
                                <th>İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($classroomController->getClassroomsList() as $classroom): ?>
                                <tr>
                                    <td><?= $classroom->id ?></td>
                                    <td><?= $classroom->name ?></td>
                                    <td><?= $classroom->class_size ?></td>
                                    <td><?= $classroom->exam_size ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-primary">İşlemler</button>
                                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                                                <span class="sr-only">İşlemler listesi</span>
                                            </button>
                                            <div class="dropdown-menu" role="menu" style="">
                                                <a class="dropdown-item" href="#">Gör</a>
                                                <a class="dropdown-item" href="/admin/editclassroom/<?=$classroom->id?>">Düzenle</a>
                                                <div class="dropdown-divider"></div>
                                                <form action="/ajax/deleteclassroom/<?=$classroom->id?>" class="ajaxFormDelete" id="deleteProgram-<?=$classroom->id?>" method="post">
                                                    <input type="hidden" name="id" value="<?=$classroom->id?>">
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
