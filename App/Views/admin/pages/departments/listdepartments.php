<?php
/**
 * @var \App\Controllers\DepartmentController $departmentController
 * @var \App\Models\Department $department
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
                        <li class="breadcrumb-item">Bölüm İşlemleri</li>
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
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                        <tr>
                            <th>İd</th>
                            <th>Adı</th>
                            <th>Bölüm Başkanı</th>
                            <th class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($departmentController->getDepartmentsList() as $department): ?>
                            <tr>
                                <td><?= $department->id ?></td>
                                <td><?= $department->name ?></td>
                                <td><?= $department->getChairperson()->getFullName() ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-primary dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            İşlemler
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/department/<?=$department->id?>">Gör</a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/editdepartment/<?=$department->id?>">Düzenle</a>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="/ajax/deletedepartment/<?=$department->id?>"
                                                      class="ajaxFormDelete"
                                                      id="deleteProgram-<?=$department->id?>"
                                                      method="post">
                                                    <input type="hidden" name="id"
                                                           value="<?=$department->id?>">
                                                    <input type="submit" class="dropdown-item" value="Sil">
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
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