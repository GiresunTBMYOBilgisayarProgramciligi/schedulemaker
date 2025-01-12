<?php
/**
 * @var \App\Models\Program $program
 * @var array $departments \App\Models\Department->getDepartments())
 * @var \App\Models\Department $department
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $lecturer
 * @var \App\Models\Lesson $lesson
 * @var \App\Controllers\LessonController $lessonController
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
                        <li class="breadcrumb-item">Ders İşlemleri</li>
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
                        <form action="/ajax/updateLesson" method="post" class="ajaxForm"
                              title="Ders Bilgilerini Güncelle">
                            <input type="hidden" name="id" value="<?= $lesson->id ?>">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label"  for="code">Kodu</label>
                                            <input type="text" class="form-control" id="code" name="code"
                                                   placeholder="Kodu"
                                                   value="<?= $lesson->code ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label"  for="type">Türü</label>
                                            <select class="form-control" id="type" name="type">
                                                <?php foreach ($lessonController->getTypeList() as $type): ?>
                                                    <option value="<?= $type ?>"
                                                        <?= $type == $lesson->type ? "selected" : "" ?>><?= $type ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label"  for="season">Dönemi</label>
                                            <select class="form-control" id="season" name="season">
                                                <?php foreach ($lessonController->getSeasonList() as $season): ?>
                                                    <option value="<?= $season ?>"
                                                        <?= $season == $lesson->season ? "selected" : "" ?>><?= $season ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"  for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı" value="<?= $lesson->name ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"  for="password">Dersin Hocası</label>
                                            <select class="form-control" id="lecturer_id" name="lecturer_id">
                                                <?php foreach ($userController->getLecturerList() as $lecturer): ?>
                                                    <option value="<?= $lecturer->id ?>"
                                                        <?= $lecturer->id == $lesson->lecturer_id ? "selected" : "" ?>><?= $lecturer->getFullName() ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label"  for="size">Mevcut</label>
                                            <input type="number" class="form-control" id="size" name="size"
                                                   placeholder="Mevcut" value="<?= $lesson->size ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label"  for="hours">Ders Saati</label>
                                            <input type="number" class="form-control" id="hours" name="hours"
                                                   placeholder="Ders Saati" value="<?= $lesson->hours ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"  for="department_id">Bölüm</label>
                                            <select class="form-control" id="department_id" name="department_id">
                                                <?php array_unshift($departments, (object)["id" => 0, "name" => "Bölüm Seçiniz"]);
                                                foreach ($departments as $department): ?>
                                                    <option value="<?= $department->id ?>"
                                                        <?= $department->id == $lesson->department_id ? 'selected' : '' ?>>
                                                        <?= $department->name ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"  for="program_id">Program</label>
                                            <select class="form-control" id="program_id" name="program_id">
                                                <?php foreach ($lesson->getDepartmentProgramsList() as $program): ?>
                                                    <option value="<?= $program->id ?>"
                                                        <?= $program->id == $lesson->program_id ? 'selected' : '' ?>>
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
