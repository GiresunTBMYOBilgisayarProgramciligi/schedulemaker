<?php
/**
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var string $page_title
 * @var array $users
 */

use App\Core\Gate;
use App\Models\User;
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= $page_title ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Liste</li>
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
                        <div class="card-header">
                            <h3 class="card-title">Kullanıcılar</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", User::class)): ?>
                                <a href="/admin/adduser" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Kullanıcı Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="user-list-table" class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <!--<th scope="col">İd</th>-->
                                <th scope="col">Ünvanı</th>
                                <th scope="col">Adı</th>
                                <th scope="col">Soyadı</th>
                                <th scope="col">e-Posta</th>
                                <th scope="col" class="filterable">Üst Birim</th>
                                <th scope="col" class="filterable">Bölüm</th>
                                <th scope="col" class="filterable">Program</th>
                                <th scope="col" class="filterable">Yetki</th>
                                <!--<ths cope="col">Kayıt Tarihi</th>-->
                                <th scope="col" class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <!--<td><?php /*= $user->id */ ?></td>-->
                                    <td><?= $user->title ?></td>
                                    <td><a href="/admin/profile/<?= $user->id ?>" class="text-dark" title="Görüntüle"><?= $user->name ?></a></td>
                                    <td><a href="/admin/profile/<?= $user->id ?>" class="text-dark" title="Görüntüle"><?= $user->last_name ?></a></td>
                                    <td><?= $user->mail ?></td>
                                    <td><?= $user->unit->name ?? '' ?></td>
                                    <td><?= $user->department->name ?? '' ?></td>
                                    <td><?= $user->program->name ?? '' ?></td>
                                    <td><?= $user->getRoleName() ?></td>
                                    <!--<td><?php /*= $user->getRegisterDate() */ ?></td>-->
                                    <td class="text-center">
                                        <a href="/admin/edituser/<?= $user->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (Gate::check("delete", $user)): ?>
                                            <form action="/ajax/deleteuser/<?= $user->id ?>" class="ajaxFormDelete d-inline"
                                                  id="deleteUser-<?= $user->id ?>" method="post">
                                                <input type="hidden" name="id" value="<?= $user->id ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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