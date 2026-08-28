<?php
/**
 * @var string $page_title
 * @var \App\Models\Unit[] $units
 * @var array $unitTypes
 */
use App\Core\Gate;
use App\Models\Unit;
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item active">Birim Listesi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Birimler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Unit::class)): ?>
                                <a href="/admin/addunit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Birim Ekle
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
                                        <th>#</th>
                                        <th>Ad</th>
                                        <th>Tür</th>
                                        <th>Müdür / Dekan</th>
                                        <th>Durum</th>
                                        <th class="text-center no-export">İşlemler</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($units as $unit): ?>
                                        <tr>
                                            <td class="text-center no-export">
                                                <input type="checkbox" class="form-check-input bulk-select-row" data-id="<?= $unit->id ?>">
                                            </td>
                                            <td><?= $unit->id ?></td>
                                            <td><a href="/admin/unit/<?= $unit->id ?>" class="text-dark" title="Görüntüle"><?= htmlspecialchars($unit->name) ?></a></td>
                                            <td><?= $unit->getTypeName() ?></td>
                                            <td><?= $unit->manager ? htmlspecialchars($unit->manager->getFullName()) : '<span class="text-muted fst-italic">Atanmamış</span>' ?></td>
                                            <td>
                                                <?php if ($unit->active): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (Gate::check("update", $unit)): ?>
                                                <a href="/admin/editunit/<?= $unit->id ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (Gate::check("delete", $unit)): ?>
                                                <form action="/ajax/deleteunit/<?= $unit->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteUnit-<?= $unit->id ?>"
                                                      method="post">
                                                    <input type="hidden" name="id" value="<?= $unit->id ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil">
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
            </div>
        </div>
    </div>
</main>
<!--end::App Main-->

<script src="/assets/js/bulkActions.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    BulkActions.init({
        entity: 'unit',
        deleteUrl: '/ajax/bulkDeleteUnits',
        updateUrl: '/ajax/bulkUpdateUnits',
        deleteConfirmMessage: 'Seçili birimleri silmek istediğinize emin misiniz?',
        editableFields: [
            { name: 'active', label: 'Aktiflik Durumu', type: 'switch' }
        ]
    });
});
</script>
