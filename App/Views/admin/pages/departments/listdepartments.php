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
                                <a href="/admin/adddepartment" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Bölüm Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <!--begin::Bulk Actions Toolbar-->
                            <div id="bulkActionsToolbar" class="alert alert-info d-flex align-items-center justify-content-between mb-3 d-none">
                                <div>
                                    <i class="bi bi-check-square me-2"></i>
                                    <span><strong><span id="bulkSelectedCount">0</span></strong> kayıt seçildi</span>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-warning" id="bulkEditBtn">
                                        <i class="bi bi-pencil me-1"></i> Toplu Düzenle
                                    </button>
                                    <button type="button" class="btn btn-danger" id="bulkDeleteBtn">
                                        <i class="bi bi-trash me-1"></i> Toplu Sil
                                    </button>
                                </div>
                            </div>
                            <!--end::Bulk Actions Toolbar-->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped dataTable">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="no-sort no-export text-center" style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="bulkSelectAll">
                                        </th>
                                        <th>İd</th>
                                        <th>Adı</th>
                                        <th>Bölüm Başkanı</th>
                                        <th class="filterable">Üst Birim</th>
                                        <th>Aktif</th>
                                        <th class="text-center no-export">İşlemler</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td class="text-center no-export">
                                                <input type="checkbox" class="form-check-input bulk-select-row" data-id="<?= $department->id ?>">
                                            </td>
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
                                                <?php if (Gate::check("update", $department)): ?>
                                                <a href="/admin/editdepartment/<?= $department->id ?>" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (Gate::check("delete", $department)): ?>
                                                <form action="/ajax/deletedepartment/<?= $department->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteProgram-<?= $department->id ?>"
                                                      method="post"
                                                      data-confirm-message="Bölümü sildiğinizde altındaki tüm programlar ve bu programlara ait dersler de silinecektir. Devam etmek istiyor musunuz?">
                                                    <input type="hidden" name="id" value="<?= $department->id ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?></tbody>
                                </table>
                            </div>
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

<script src="/assets/js/bulkActions.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    BulkActions.init({
        entity: 'department',
        deleteUrl: '/ajax/bulkDeleteDepartments',
        updateUrl: '/ajax/bulkUpdateDepartments',
        deleteConfirmMessage: 'Seçili bölümleri silmek istediğinize emin misiniz? Altındaki tüm programlar ve dersler de silinecektir.',
        editableFields: [
            { name: 'active', label: 'Aktiflik Durumu', type: 'switch' },
            { name: 'unit_id', label: 'Üst Birim', type: 'select', options: <?= json_encode($unitOptions ?? []) ?> }
        ]
    });
});
</script>