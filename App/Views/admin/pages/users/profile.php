<?php
/**
 * @var \App\Models\User $user
 * @var \App\Controllers\UserController $userController
 * @var array $departments
 * @var string $page_title
 * @var string $scheduleHTML
 */

use App\Core\Gate;

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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Profil</li>
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
                <div class="col-md-3">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="profile-user-img img-fluid img-circle"
                                     src="<?= $user->getGravatarURL(150) ?>" alt="User profile picture">
                            </div>

                            <h3 class="profile-username text-center"><?= $user->getFullName() ?></h3>

                            <p class="text-muted text-center"><?= $user->title ?></p>

                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Ders</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= count($user->lessons) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Öğrenci sayısı</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= array_reduce($user->lessons, fn($sum, $l) => $sum + ($l->size ?? 0), 0) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Ders Saati</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= array_reduce($user->lessons, fn($sum, $l) => $sum + ($l->hours ?? 0), 0) ?></span>
                                </li>
                            </ul>

                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">
                            <?php if (Gate::check("delete",$user)): ?>
                                <form action="/ajax/deleteuser/<?= $user->id ?>"
                                      class="ajaxFormDelete"
                                      id="deleteUser-<?= $user->id ?>"
                                      method="post">
                                    <input type="hidden" name="id"
                                           value="<?= $user->id ?>">
                                    <input form="deleteUser-<?= $user->id ?>" type="submit" class="btn btn-danger"
                                           value="Kullanıcıyı Sil">
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-9">
                    <!-- About Me Box  -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Bilgilerim</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <form action="/ajax/updateUser" method="post" class="ajaxForm updateForm"
                                  title="Bilgileri Güncelle">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="hidden" name="id" value="<?= $user->id ?>">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı"
                                                   value="<?= htmlspecialchars($user->name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="last_name">Soyadı</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   placeholder="Soyadı"
                                                   value="<?= htmlspecialchars($user->last_name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="mail">e-Posta</label>
                                            <input type="email" class="form-control" id="mail" name="mail"
                                                   placeholder="e-Posta"
                                                   value="<?= htmlspecialchars($user->mail ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="password">Şifre</label>
                                            <input type="password" class="form-control" id="password" name="password"
                                                   placeholder="Şifre">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="role">Rol</label>
                                            <select class="form-select" id="role" name="role">
                                                <?php foreach ($userController->getRoleList() as $role => $value): ?>
                                                    <option value="<?= $role ?>"
                                                        <?= $role == $user->role ? "selected" : "" ?>><?= $value ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="title">Ünvan</label>
                                            <select class="form-select" id="title" name="title">
                                                <?php $titleList = $userController->getTitleList();
                                                array_unshift($titleList, "");
                                                foreach ($titleList as $title): ?>
                                                    <option value="<?= $title ?>"
                                                        <?= $title == $user->title ? "selected" : "" ?>><?= $title ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="department_id">Bölüm</label>
                                            <select class="form-select tom-select" id="department_id" name="department_id">
                                                <?php array_unshift($departments, (object)["id" => 0, "name" => "Bölüm Seçiniz"]);
                                                foreach ($departments as $department): ?>
                                                    <option value="<?= $department->id ?>"
                                                        <?= $department->id == $user->department_id ? 'selected' : '' ?>>
                                                        <?= $department->name ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="program_id">Program</label>
                                            <select class="form-select" id="program_id" name="program_id">
                                                <?php foreach ($user->getDepartmentProgramsList() as $program): ?>
                                                    <option value="<?= $program->id ?>"
                                                        <?= $program->id == $user->program_id ? 'selected' : '' ?>>
                                                        <?= $program->name ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">

                            <button type="submit" class="btn btn-primary">Güncelle</button>

                        </div>

                        </form>
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline card-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="lessons-tab" data-bs-toggle="pill" href="#lessons" role="tab" aria-controls="lessons" aria-selected="true">Derslerim</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="schedule-tab" data-bs-toggle="pill" href="#schedule" role="tab" aria-controls="schedule" aria-selected="false">Ders Programım</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="exams-tab" data-bs-toggle="pill" href="#exams" role="tab" aria-controls="exams" aria-selected="false">Sınav Programım</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="profileTabsContent">
                                <div class="tab-pane fade show active" id="lessons" role="tabpanel" aria-labelledby="lessons-tab">
                                    <div class="row">
                                        <?php foreach ($user->lessons as $lesson): ?>
                                            <div class="col-md-3 col-sm-6 p-1">
                                                <a href="/admin/lesson/<?= $lesson->id ?>" class="text-decoration-none text-reset">
                                                    <div class="lesson-card w-100 <?= $lesson->getScheduleCSSClass() ?? '' ?>" style="cursor: pointer;">
                                                        <span class="lesson-name" title="<?= $lesson->name ?>">
                                                            <?= $lesson->code ?> - <?= $lesson->name ?>
                                                        </span>
                                                        <div class="lesson-meta">
                                                            <span class="lesson-lecturer">
                                                                <?= $lesson->program->name ?? "-" ?>
                                                            </span>
                                                            <span class="lesson-classroom">
                                                                <?= $lesson->hours ?> Saat / <?= $lesson->size ?> Kişi
                                                            </span>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($user->lessons)): ?>
                                            <div class="col-12 text-center text-muted">Hoca üzerine kayıtlı ders bulunamadı.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                                    <?= $scheduleHTML ?>
                                </div>
                                <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                                    <!-- Nested Tabs for Exams -->
                                    <ul class="nav nav-tabs mb-3" id="examTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="midterm-tab" data-bs-toggle="tab" href="#midterm" role="tab">Ara Sınav</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="final-tab" data-bs-toggle="tab" href="#final" role="tab">Final</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="makeup-tab" data-bs-toggle="tab" href="#makeup" role="tab">Bütünleme</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="examTabsContent">
                                        <div class="tab-pane fade show active" id="midterm" role="tabpanel">
                                            <?= $midtermScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="final" role="tabpanel">
                                            <?= $finalScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="makeup" role="tabpanel">
                                            <?= $makeupScheduleHTML ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->