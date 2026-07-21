<?php
/**
 * @var \App\Models\Department $department
 * @var string $page_title
 * @var array $departments
 */
use App\Core\Gate;
use App\Models\Department;
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Bölümler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Department::class)): ?>
                                <a href="/admin/adddepartment" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Bölüm Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                        <thead>
                        <tr>
                            <th>İd</th>
                            <th>Adı</th>
                            <th>Bölüm Başkanı</th>
                            <th>Üst Birim</th>
                            <th>Aktif</th>
                            <th class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td><?= $department->id ?></td>
                                <td><a href="/admin/department/<?= $department->id ?>" class="text-dark" title="Görüntüle"><?= $department->name ?></a></td>
                                <td><?= $department->chairperson?->getFullName() ?? '' ?></td>
                                <td><?= $department->unit?->name ?? '' ?></td>
                                <td>
                                    <div class="form-check form-switch ">
                                        <input name="active" class="form-check-input" type="checkbox"
                                               id="flexSwitchCheckChecked"
                                                <?= $department->active ? "checked" : "" ?> disabled>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="/admin/editdepartment/<?= $department->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="/ajax/deletedepartment/<?= $department->id ?>"
                                          class="ajaxFormDelete d-inline"
                                          id="deleteProgram-<?= $department->id ?>"
                                          method="post"
                                          data-confirm-message="Bölümü sildiğinizde altındaki tüm programlar ve bu programlara ait dersler de silinecektir. Devam etmek istiyor musunuz?">
                                        <input type="hidden" name="id" value="<?= $department->id ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
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