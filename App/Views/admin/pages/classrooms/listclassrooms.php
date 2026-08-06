<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Models\Classroom $classroom
 * @var string $page_title
 * @var array $classrooms
 */
use App\Core\Gate;
use App\Models\Classroom;
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
                        <li class="breadcrumb-item">Derslik İşlemleri</li>
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
                            <h3 class="card-title">Derslikler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Classroom::class)): ?>
                                <a href="/admin/addclassroom" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Derslik Ekle
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
                                        <th scope="col">İd</th>
                                        <th scope="col">Adı</th>
                                        <th scope="col" class="filterable">Türü</th>
                                        <th scope="col" class="filterable">Bina</th>
                                        <th scope="col">Ders Mevcudu</th>
                                        <th scope="col">Sınav Mevcudu</th>
                                        <th scope="col" class="text-center no-export">İşlemler</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($classrooms as $classroom): ?>
                                        <tr>
                                            <td class="text-center no-export">
                                                <input type="checkbox" class="form-check-input bulk-select-row" data-id="<?= $classroom->id ?>">
                                            </td>
                                            <td><?= $classroom->id ?></td>
                                            <td><a href="/admin/classroom/<?= $classroom->id ?>" class="text-dark" title="Görüntüle"><?= $classroom->name ?></a></td>
                                            <td><?= $classroom->getTypeName() ?></td>
                                            <td><?= $classroom->building->name ?? '-' ?></td>
                                            <td><?= $classroom->class_size ?></td>
                                            <td><?= $classroom->exam_size ?></td>
                                            <td class="text-center">
                                                <?php if (Gate::check("update", $classroom)): ?>
                                                <a href="/admin/editclassroom/<?= $classroom->id ?>" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (Gate::check("delete", $classroom)): ?>
                                                <form action="/ajax/deleteclassroom/<?= $classroom->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteClassroom-<?= $classroom->id ?>"
                                                      method="post">
                                                    <input type="hidden" name="id" value="<?= $classroom->id ?>">
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
        entity: 'classroom',
        deleteUrl: '/ajax/bulkDeleteClassrooms',
        updateUrl: '/ajax/bulkUpdateClassrooms',
        deleteConfirmMessage: 'Seçili derslikleri silmek istediğinize emin misiniz? Bu dersliklere ait ders programı kayıtları da temizlenecektir.',
        editableFields: [
            { name: 'building_id', label: 'Bina', type: 'select', options: <?= json_encode($buildingOptions ?? []) ?> }
        ]
    });
});
</script>
