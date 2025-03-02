<?php
/**
 * @var \App\Controllers\ProgramController $programController
 * @var \App\Models\Program $program
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var string $page_title
 * @var string $scheduleHTML
 * @var \App\Models\User $currentUser
 */

use function App\Helpers\isAuthorized;

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
                        <li class="breadcrumb-item">Program İşlemleri</li>
                        <li class="breadcrumb-item active">Program</li>
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
                    <!-- Program Bilgileri -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Program Bilgileri</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Program Adı</dt>
                                <dd class="col-sm-8"><?= $program->name ?></dd>
                                <dt class="col-sm-4">Bölüm</dt>
                                <dd class="col-sm-8">
                                    <a
                                            href="/admin/department/<?= $program->department_id ?>"> <?= $program->getDepartment()->name ?></a>
                                </dd>
                                <dt class="col-sm-4">Bölüm Başkanı</dt>
                                <dd class="col-sm-8"><a
                                            href="/admin/profile/<?= $program->getDepartment()->getChairperson()->id ?>"> <?= $program->getDepartment()->getChairperson()->getFullName() ?></a>
                                </dd>
                                <dt class="col-sm-4">Akademisyen Sayısı</dt>
                                <dd class="col-sm-8"><?= $program->getLecturerCount() ?></dd>
                                <dt class="col-sm-4">Ders Sayısı</dt>
                                <dd class="col-sm-8"><?= $program->getLessonCount() ?></dd>
                                <dt class="col-sm-4">Öğrenci Sayısı</dt>
                                <dd class="col-sm-8"><?= $program->getStudentCount() ?></dd>
                            </dl>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">
                            <?php if(isAuthorized("submanager")): ?>
                            <a href="/admin/editprogram/<?= $program->id ?>" class="btn btn-primary">Programı
                                Düzenle</a>
                            <?php endif; ?>
                            <?php if(isAuthorized("submanager",false,$program) and $currentUser->role =="department_head"): ?>
                            <a href="/admin/addlesson/<?= $program->id ?>" class="btn btn-success">Ders Ekle</a>
                            <a href="/admin/adduser/<?= $program->department_id ?>/<?= $program->id ?>"
                               class="btn btn-success">Hoca Ekle</a>
                            <?php endif; ?>
                            <?php if(isAuthorized("submanager")): ?>
                            <form action="/ajax/deleteprogram/<?= $program->id ?>" class="ajaxFormDelete d-inline"
                                  id="deleteProgram-<?= $program->id ?>" method="post">
                                <input type="hidden" name="id" value="<?= $program->id ?>">
                                <input type="submit" class="btn btn-danger" value="Sil" role="button">
                            </form>
                            <?php endif; ?>
                        </div>
                        <!-- /.card-footer -->
                    </div>
                </div>
                <div class="col-7">
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
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table id="user-list-table" class="table table-bordered table-striped dataTable ">
                                <thead>
                                <tr>
                                    <th scope="col">Ünvanı Adı Soyadı</th>
                                    <th scope="col">e-Posta</th>
                                    <?php if (isAuthorized("department_head")): ?>
                                    <th scope="col" class="text-center">İşlemler</th>
                                    <?php endif; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($program->getLecturers() as $lecturer): ?>
                                    <tr>
                                        <td><?= $lecturer->getFullName() ?></td>
                                        <td><?= $lecturer->mail ?></td>
                                        <?php if (isAuthorized("department_head")): ?>
                                        <td class="text-center">
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
                                                    <?php if (isAuthorized("submanager")):?>
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
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <?php endif; ?>
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
                    <!-- İlişkili Dersler -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">İlişkili Dersler</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
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
                                    <th scope="col">Derslik Türü</th>
                                    <th scope="col" class="text-center">İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($program->getLessons() as $lesson): ?>
                                    <tr>
                                        <td><?= $lesson->code ?></td>
                                        <td><?= $lesson->name ?></td>
                                        <td><?= $lesson->getTypeName() ?></td>
                                        <td><?= $lesson->hours ?></td>
                                        <td><?= $lesson->semester_no ?></td>
                                        <td><?= $lesson->getLecturer()->getFullName() ?></td>
                                        <td><?= $lesson->getClassroomTypeName()?></td>
                                        <td class="text-center">
                                            <?php if (isAuthorized("submanager", false, $lesson)): ?>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    İşlemler
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/lesson/<?=$lesson->id?>">Gör</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="/admin/editlesson/<?=$lesson->id?>">Düzenle</a>
                                                    </li>
                                                    <?php if (isAuthorized("submanager", false, $lesson) and $currentUser->id == $lesson->getDepartment()->chairperson_id): ?>
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
                                                                <input type="submit" class="dropdown-item"
                                                                       value="Sil">
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <?= $scheduleHTML ?>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->
