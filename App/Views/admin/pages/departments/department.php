<?php
/**
 * @var \App\Controllers\DepartmentController $departmentController
 * @var \App\Models\Department $department
 * @var string $page_title
 */
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Bölüm İşlemleri</li>
                        <li class="breadcrumb-item active">Bölüm</li>
                    </ol>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-5">
                    <!-- Bölüm Bilgileri -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Bölüm Bilgileri</h3>

                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                            <!-- /.card-tools -->
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Bölüm Adı</dt>
                                <dd class="col-sm-8"><?= $department->name ?></dd>
                                <dt class="col-sm-4">Bölüm Başkanı</dt>
                                <dd class="col-sm-8">
                                    <a href="/admin/profile/<?= $department->getChairperson()->id ?>"><?= $department->getChairperson()->getFullName() ?></a>
                                </dd>
                                <dt class="col-sm-4">Program Sayısı</dt>
                                <dd class="col-sm-8"><?= $department->getProgramCount() ?></dd>
                                <dt class="col-sm-4">Akademisyen Sayısı</dt>
                                <dd class="col-sm-8"><?= $department->getLecturerCount() ?></dd>
                                <dt class="col-sm-4">Ders Sayısı</dt>
                                <dd class="col-sm-8"><?= $department->getLessonCount() ?></dd>
                            </dl>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <a href="/admin/editdepartment/<?= $department->id ?>" class="btn btn-primary">Bölümü
                                Düzenle</a>
                            <a href="/admin/addprogram/<?= $department->id ?>" class="btn btn-success">Program Ekle</a>
                            <a href="/admin/adduser/<?= $department->id ?>" class="btn btn-success">Hoca Ekle</a>
                            <form action="/ajax/deletedepartment/<?= $department->id ?>" class="ajaxFormDelete d-inline"
                                  id="deleteProgram-<?= $department->id ?>" method="post">
                                <input type="hidden" name="id" value="<?= $department->id ?>">
                                <input type="submit" class="btn btn-danger" value="Sil" role="button">
                            </form>
                        </div>
                        <!-- /.card-footer -->
                    </div>
                </div>
                <div class="col-7">
                    <!-- İlişkili Programlar -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">İlişkili Programlar</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                            <!-- /.card-tools -->
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th scope="col">İd</th>
                                    <th scope="col">Adı</th>
                                    <th scope="col">Bölüm</th>
                                    <th scope="col">İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($department->getPrograms() as $program): ?>
                                    <tr>
                                        <td><?= $program->id ?></td>
                                        <td><?= $program->name ?></td>
                                        <td><?= $program->getDepartment()->name ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    İşlemler
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/program/<?= $program->id ?>">Gör</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/editprogram/<?= $program->id ?>">Düzenle</a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form action="/ajax/deleteprogram/<?= $program->id ?>"
                                                              class="ajaxFormDelete"
                                                              id="deleteProgram-<?= $program->id ?>"
                                                              method="post">
                                                            <input type="hidden" name="id"
                                                                   value="<?= $program->id ?>">
                                                            <input type="submit" class="dropdown-item" value="Sil">
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <!-- İlişkili Akademisyenler-->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">İlişkili Akademisyenler</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                            <!-- /.card-tools -->
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table id="user-list-table" class="table table-bordered table-striped dataTable ">
                                <thead>
                                <tr>
                                    <th>Ünvanı Adı Soyadı</th>
                                    <th>e-Posta</th>
                                    <th>Program</th>
                                    <th>İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($department->getLecturers() as $lecturer): ?>
                                    <tr>
                                        <td><?= $lecturer->getFullName() ?></td>
                                        <td><?= $lecturer->mail ?></td>
                                        <td><?= $lecturer->getProgramName() ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    İşlemler
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/profile/<?= $lecturer->id ?>">Gör</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/edituser/<?= $lecturer->id ?>">Düzenle</a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form action="/ajax/deleteuser/<?= $lecturer->id ?>"
                                                              class="ajaxFormDelete"
                                                              id="deleteUser-<?= $lecturer->id ?>"
                                                              method="post">
                                                            <input type="hidden" name="id"
                                                                   value="<?= $lecturer->id ?>">
                                                            <input type="submit" class="dropdown-item" value="Sil">
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card  card-primary">
                        <div class="card-header">
                            <h3 class="card-title">İlişkili Dersler</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                            <!-- /.card-tools -->
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th scope="col">Kodu</th>
                                    <th scope="col">Adı</th>
                                    <th scope="col">Türü</th>
                                    <th scope="col">Saati</th>
                                    <th scope="col">Dönemi</th>
                                    <th scope="col">Hocası</th>
                                    <th scope="col">Program</th>
                                    <th scope="col">İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($department->getLessons() as $lesson): ?>
                                    <tr>
                                        <td><?= $lesson->code ?></td>
                                        <td><?= $lesson->name ?></td>
                                        <td><?= $lesson->type ?></td>
                                        <td><?= $lesson->hours ?></td>
                                        <td><?= $lesson->season ?></td>
                                        <td><?= $lesson->getLecturer()->getFullName() ?></td>
                                        <td><?= $lesson->getProgam()->name ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    İşlemler
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/lesson/<?= $lesson->id ?>">Gör</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/editlesson/<?= $lesson->id ?>">Düzenle</a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form action="/ajax/deletelesson/<?= $lesson->id ?>"
                                                              class="ajaxFormDelete"
                                                              id="deleteLesson-<?= $lesson->id ?>"
                                                              method="post">
                                                            <input type="hidden" name="id"
                                                                   value="<?= $lesson->id ?>">
                                                            <input type="submit" class="dropdown-item" value="Sil">
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row Program Satırı-->
            <div class="row">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Program</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                    <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
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
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->