<?php
/**
 * @var \App\Models\User $user
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\Lesson $lesson Hocaya ait ders listesi oluşturulurken kullanılıyor
 * @var \App\Controllers\ScheduleController $scheduleController
 * @var array $departments
 * todo users klasörüne taşınabilir
 */

?>
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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Profil</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
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

                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Ders</b> <a class="float-right">1,322</a>
                                </li>
                                <li class="list-group-item">
                                    <b>Öğrenci sayısı</b> <a class="float-right">543</a>
                                </li>
                                <li class="list-group-item">
                                    <b>Ders Saati</b> <a class="float-right">13,287</a>
                                </li>
                            </ul>

                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-9">
                    <!-- About Me Box  -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Bilgilerim</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <form action="/ajax/updateUser" method="post" class="ajaxForm"
                                  title="Bilgileri Güncelle">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="hidden" name="id" value="<?= $user->id ?>">
                                        <div class="form-group">
                                            <label for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı"
                                                   value="<?= htmlspecialchars($user->name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="last_name">Soyadı</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   placeholder="Soyadı"
                                                   value="<?= htmlspecialchars($user->last_name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="mail">e-Posta</label>
                                            <input type="email" class="form-control" id="mail" name="mail"
                                                   placeholder="e-Posta"
                                                   value="<?= htmlspecialchars($user->mail ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="password">Şifre</label>
                                            <input type="password" class="form-control" id="password" name="password"
                                                   placeholder="Şifre">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role">Rol</label>
                                            <select class="form-control" id="role" name="role">
                                                <?php foreach ($userController->getRoleList() as $role => $value): ?>
                                                    <option value="<?= $role ?>"
                                                        <?= $role == $user->role ? "selected" : "" ?>><?= $value ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="title">Ünvan</label>
                                            <select class="form-control" id="title" name="title">
                                                <?php foreach ($userController->getTitleList() as $title): ?>
                                                    <option value="<?= $title ?>"
                                                        <?= $title == $user->title ? "selected" : "" ?>><?= $title ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="department_id">Bölüm</label>
                                            <select class="form-control" id="department_id" name="department_id">
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
                                        <div class="form-group">
                                            <label for="program_id">Program</label>
                                            <select class="form-control" id="program_id" name="program_id">
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
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Güncelle</button>
                        </div>

                        </form>
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-12">
                    <!-- card Derslerim -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Derslerim</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <!-- Burada dersler listelenecek -->
                            <div class="row">
                                <?php foreach ($user->getLessonsList() as $lesson): ?>
                                    <div class="col-md-4">
                                        <a href="/admin/lesson/<?= $lesson->id ?>" class="text-dark">
                                            <div class="callout callout-info">
                                                <h5><?= $lesson->code ?></h5>
                                                <p><?= $lesson->name ?></p>
                                                <p><?= $lesson->getDepartment()->name . " - " . $lesson->getProgam()->name ?></p>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
            <!-- /.row -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-primary">
                        <div class="card-header ">
                            <h3 class="card-title">Program</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div><!-- /.card-header -->
                        <div class="card-body">
                            <?= $scheduleController->createScheduleTable(["owner_type" => "user", "owner_id" => $user->id]) ?>
                        </div><!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>