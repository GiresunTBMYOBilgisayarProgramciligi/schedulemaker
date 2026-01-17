<?php
/**
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var string $page_title
 * @var array $users
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
                    <table id="user-list-table" class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <!--<th scope="col">İd</th>-->
                                <th scope="col">Ünvanı Adı Soyadı</th>
                                <th scope="col">e-Posta</th>
                                <th scope="col" class="filterable">Bölüm</th>
                                <th scope="col" class="filterable">Program</th>
                                <th scope="col" class="filterable">Yetki</th>
                                <!--<ths cope="col">Kayıt Tarihi</th>-->
                                <th scope="col">Son Giriş Tarihi</th>
                                <th scope="col" class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <!--<td><?php /*= $user->id */ ?></td>-->
                                    <td><?= $user->getFullName() ?></td>
                                    <td><?= $user->mail ?></td>
                                    <td><?= $user->department->name ?? '' ?></td>
                                    <td><?= $user->program->name ?? '' ?></td>
                                    <td><?= $user->getRoleName() ?></td>
                                    <!--<td><?php /*= $user->getRegisterDate() */ ?></td>-->
                                    <td><?= $user->getLastLogin() ?></td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-primary dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                İşlemler
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="/admin/profile/<?= $user->id ?>">Gör</a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item"
                                                        href="/admin/edituser/<?= $user->id ?>">Düzenle</a>
                                                </li>
                                                <?php if (Gate::check("delete", $user)): ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form action="/ajax/deleteuser/<?= $user->id ?>" class="ajaxFormDelete"
                                                            id="deleteUser-<?= $user->id ?>" method="post">
                                                            <input type="hidden" name="id" value="<?= $user->id ?>">
                                                            <input type="submit" class="dropdown-item" value="Sil">
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->