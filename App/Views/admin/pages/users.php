<?php
/**
 * @var \App\Models\UsersController $usersController
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var array $programs \App\Models\Program->getPrograms())
 * @var array $departments \App\Models\Department->getDepartments())
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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Liste</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content ">
        <div class="card card-solid">
            <div class="card-body pb-0">
                <div class="row">
                    <!-- todo Kullanıcı listelerine filtre eklenebilmeli. Sadece şu bölümün hocaları, sadece yöneticiler filan. Burada data table kullanırım-->
                    <table id="user-list-table" class="table">
                        <thead>
                        <tr>
                            <th>İd</th>
                            <th>e-Posta</th>
                            <th>Adı</th>
                            <th>Soyadı</th>
                            <th>Kayıt Tarihi</th>
                            <th>Son Giriş Tarihi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usersController->get_users_list() as $user): ?>
                            <tr class="odd">
                                <td><?= $user->id ?></td>
                                <td><?= $user->mail ?></td>
                                <td><?= $user->name ?></td>
                                <td><?= $user->last_name ?></td>
                                <td><?= $user->register_date->format('Y-m-d H:i:s') ?></td>
                                <td><?php $user->last_login->format('Y-m-d H:i:s')  ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>

                    <!--<div class="col-12 col-sm-6 col-md-4 d-flex align-items-stretch flex-column">
                            <div class="card  d-flex flex-fill">
                                <div class="card-header text-muted border-bottom-0">
                                    <?php /*= $user->title */ ?>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-7">
                                            <h2 class="lead"><b><?php /*= $user->getFullName() */ ?></b></h2>
                                            <br>
                                            <p class="text-sm">
                                                <b>Bölüm: </b>
                                                <?php /*= $user->getDepartmentName() */ ?>

                                                <b>Program: </b>
                                                <?php /*= $user->getProgramName() */ ?>
                                            </p>
                                            <ul class="ml-4 mb-0 fa-ul text-muted">
                                                <li class="small"><span class="fa-li"><i
                                                                class="fas fa-lg fa-user-tag"></i></span>
                                                    Rol: <?php /*= $user->getRoleName() */ ?></li>
                                                <li class="small"><span class="fa-li"><i
                                                                class="fas fa-lg fa-business-time"></i></span>
                                                    Son
                                                    Giriş: <?php /*= $user->last_login->format('Y-m-d H:i:s') */ ?>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-5 text-center">
                                            <img src="<?php /*= $user->getGravatarURL(100) */ ?>"
                                                 alt="user-avatar" class="img-circle img-fluid">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="text-right">
                                        <a href="/admin/profile/<?php /*= $user->id */ ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-user"></i> Profil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>-->

                </div>
            </div>
            <div class="card-footer">
                <nav aria-label="Contacts Page Navigation">
                    <ul class="pagination justify-content-center m-0">
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item"><a class="page-link" href="#">4</a></li>
                        <li class="page-item"><a class="page-link" href="#">5</a></li>
                        <li class="page-item"><a class="page-link" href="#">6</a></li>
                        <li class="page-item"><a class="page-link" href="#">7</a></li>
                        <li class="page-item"><a class="page-link" href="#">8</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
