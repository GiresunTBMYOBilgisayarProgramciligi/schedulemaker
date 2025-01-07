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
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Ders</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Ders Bilgileri -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Derslik Bilgileri</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Derslik Adı</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($classroom->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Ders Mevcudu</dt>
                                <dd class="col-sm-8"><?= $classroom->class_size ?></dd>
                                <dt class="col-sm-4">Sınav Mevcudu</dt>
                                <dd class="col-sm-8"><?= $classroom->exam_size ?></dd>
                            </dl>
                        </div>
                        <div class="card-footer">
                            <a href="/admin/editclassroom/<?= $classroom->id ?>" class="btn btn-primary">Dersliği Düzenle</a>
                            <form action="/ajax/deleteclassroom/<?= $classroom->id ?>" class="ajaxFormDelete d-inline" method="post">
                                <input type="hidden" name="id" value="<?= $classroom->id ?>">
                                <input type="submit" class="btn btn-danger" value="Sil">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Program</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">

                        </div>
                        <div class="card-footer">

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
