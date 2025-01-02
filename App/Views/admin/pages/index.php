<?php
/**
 * @var \App\Controllers\UsersController $usersController
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 */
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Başlangıç</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Ana Sayfa</a></li>
                        <li class="breadcrumb-item active">Başlangıç</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Small boxes (Stat box) -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <!-- small box -->
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $usersController->getCount() ?></h3>

                            <p>Öğretim Elemanı</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-3 col-6">
                    <!-- small box -->
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>15</h3>

                            <p>Derslik</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-3 col-6">
                    <!-- small box -->
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>44</h3>

                            <p>Ders</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
                <div class="col-lg-3 col-6">
                    <!-- small box -->
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>850</h3>

                            <p>Öğrenci</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
            </div>
            <!-- /.row -->
            <!-- Main row -->
            <div class="row"><!--todo Buradaki liste kullanıcıya göre güncellenenebilir. Sadece kendi bölümünün hocaları gözükür. filan-->
                <?php foreach ($usersController->getUsersList() as $user): ?>
                    <div class="col-12 col-sm-6 col-md-4 d-flex align-items-stretch flex-column">
                        <div class="card  d-flex flex-fill">
                            <div class="card-header text-muted border-bottom-0">
                                <?= $user->title ?>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-7">
                                        <h2 class="lead"><b><?= $user->getFullName() ?></b></h2>
                                        <br>
                                        <p class="text-sm">
                                            <b>Bölüm: </b>
                                            <?= $user->getDepartmentName() ?>

                                            <b>Program: </b>
                                            <?= $user->getProgramName() ?>
                                        </p>
                                        <ul class="ml-4 mb-0 fa-ul text-muted">
                                            <li class="small"><span class="fa-li"><i
                                                            class="fas fa-lg fa-user-tag"></i></span>
                                                Rol: <?= $user->getRoleName() ?></li>
                                            <li class="small"><span class="fa-li"><i
                                                            class="fas fa-lg fa-business-time"></i></span>
                                                Son
                                                Giriş: <?= $user->getLastLogin() ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-5 text-center">
                                        <img src="<?= $user->getGravatarURL(100) ?>"
                                             alt="user-avatar" class="img-circle img-fluid">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="text-right">
                                    <a href="/admin/profile/<?= $user->id ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-user"></i> Profil
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- /.row (main row) -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->