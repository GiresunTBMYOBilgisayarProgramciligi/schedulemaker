<div class="content-wrapper" style="min-height: 1604.8px;">
    <?php
    /**
     * @var \App\Models\User $user
     * @var \App\Models\UsersController $usersController
     * @var array $programs \App\Models\Program->getPrograms())
     * @var array $departments \App\Models\Department->getDepartments())
     */

    ?>
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
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Derslerim</h3>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <!-- Burada dersler listelenecek -->
                            <div class="row">
                                <ul class="list-group list-group-unbordered">
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-book mr-1"></i> BİLP-101 Programlama Temelleri
                                        </strong>
                                        <p class="text-muted">
                                            Bilgisayar Teknolojileri - Bilgisayar Programcılığı
                                        </p>

                                    </li>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-book mr-1"></i> BİLP-102 Programlama Temelleri 2
                                        </strong>
                                        <p class="text-muted">
                                            Bilgisayar Teknolojileri - Bilgisayar Programcılığı
                                        </p>

                                    </li>
                                </ul>

                            </div>


                        </div>
                    </div>

                </div>
                <!-- /.col -->
                <div class="col-md-9">
                    <!-- About Me Box  -->
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Bilgilerim</h3>
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
                                            <?php $user_role = htmlspecialchars($user->role ?? ''); ?>
                                            <select class="form-control" id="role" name="role">
                                                <option value="user" <?= $user_role == "user" ? 'selected' : '' ?>>
                                                    Kullanıcı
                                                </option>
                                                <option value="lecturer" <?= $user_role == "lecturer" ? 'selected' : '' ?>>
                                                    Akademisyen
                                                </option>
                                                <option value="admin" <?= $user_role == "admin" ? 'selected' : '' ?>>
                                                    Yönetici
                                                </option>
                                                <option value="department_head" <?= $user_role == "department_head" ? 'selected' : '' ?>>
                                                    Bölüm Başkanı
                                                </option>
                                                <option value="manager" <?= $user_role == "manager" ? 'selected' : '' ?>>
                                                    Müdür
                                                </option>
                                                <option value="submanager" <?= $user_role == "submanager" ? 'selected' : '' ?>>
                                                    Müdür Yardımcısı
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="title">Ünvan</label>
                                            <?php $title = htmlspecialchars($user->title ?? ''); ?>
                                            <select class="form-control" id="title" name="title">
                                                <option value="Öğr. Gör." <?= $title == "Öğr. Gör." ? 'selected' : '' ?>>
                                                    Öğr.
                                                    Gör.
                                                </option>
                                                <option value="Öğr. Gör. Dr." <?= $title == "Öğr. Gör. Dr." ? 'selected' : '' ?>>
                                                    Öğr. Gör. Dr.
                                                </option>
                                                <option value="Dr. Öğretim Üyesi" <?= $title == "Dr. Öğretim Üyesi" ? 'selected' : '' ?>>
                                                    Dr. Öğretim Üyesi
                                                </option>
                                                <option value="Doç. Dr. " <?= $title == "Doç. Dr. " ? 'selected' : '' ?>>
                                                    Doç.
                                                    Dr.
                                                </option>
                                                <option value="Prof. Dr." <?= $title == "Prof. Dr." ? 'selected' : '' ?>>
                                                    Prof.
                                                    Dr.
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="department_id">Bölüm</label>
                                            <?php $department_id = htmlspecialchars($user->department_id ?? ''); ?>
                                            <select class="form-control" id="department_id" name="department_id">
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?= $department['id'] ?>"
                                                        <?= $department['id'] == $department_id ? 'selected' : '' ?>>
                                                        <?= $department['name'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="program_id">Program</label>
                                            <?php $program_id = htmlspecialchars($user->program_id ?? ''); ?>
                                            <select class="form-control" id="program_id" name="program_id">
                                                <?php foreach ($programs as $program): ?>
                                                    <option value="<?= $program['id'] ?>"
                                                        <?= $program['id'] == $program_id ? 'selected' : '' ?>>
                                                        <?= $program['name'] ?>
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
                    <div class="card card-primary">
                        <div class="card-header ">
                            <h3 class="card-title">Program</h3>
                        </div><!-- /.card-header -->
                        <div class="card-body">
                            Ders Programı Haftalık takvim

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