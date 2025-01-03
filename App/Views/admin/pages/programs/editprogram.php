<?php
/**
 * @var array $departments \App\Models\Department->getDepartmentsList())
 * @var \App\Models\Department $department
 * @var \App\Models\Program $program
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
                        <li class="breadcrumb-item">Program İşlemleri</li>
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
            <form action="/ajax/updateprogram" method="post" class="ajaxForm" title="Program Düzenle">
                <input type="hidden" name="id" value="<?= $program->id ?>">
                <div class="card-body pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Adı</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               placeholder="Adı" value="<?= $program->name ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department_id">Bölüm</label>
                                        <select class="form-control" id="department_id" name="department_id">
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= $department->id ?>"
                                                    <?= $department->id == $program->department_id ? "selected" : "" ?>><?= $department->name ?></option>
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
