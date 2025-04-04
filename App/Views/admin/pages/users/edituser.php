<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $user düzenlenecek kullanıcı user değişkeni
 * @var \App\Controllers\ProgramController $programController
 * @var \App\Models\Program $program
 * @var array $departments \App\Models\Department->getDepartments())
 * @var $page_title
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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Düzenle</li>
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
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <form action="/ajax/updateUser" method="post" class="ajaxForm updateForm"
                              title="Kullanıcı Bilgilerini Güncelle">
                            <div class="card-body pb-0">
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
                                            <select class="form-select" id="role"
                                                    name="role" <?= isAuthorized("submanager") ? "" : "disabled" ?>>
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
                                            <select class="form-select" id="title" name="title" <?= isAuthorized("submanager") ? "" : "disabled" ?>>
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
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Güncelle</button>
                            </div>
                        </form>
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