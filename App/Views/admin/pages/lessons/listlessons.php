<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 * @var array $lessons
 * @var string $page_title
 */
use App\Core\Gate;
use App\Models\Lesson;
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
                        <li class="breadcrumb-item">Ders İşlemleri</li>
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
                            <h3 class="card-title">Dersler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Lesson::class)): ?>
                                <a href="/admin/addlesson" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Ders Ekle
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
                                        <th scope="col">Kodu</th>
                                        <th scope="col" class="filterable">Adı</th>
                                        <th scope="col" class="filterable">Türü</th>
                                        <th scope="col">Mevcudu</th>
                                        <th scope="col">Saati</th>
                                        <th scope="col" class="filterable">Yarıyılı</th>
                                        <th scope="col" class="filterable">Hocası</th>
                                        <th scope="col" class="filterable">Bölüm</th>
                                        <th scope="col" class="filterable">Program</th>
                                        <th scope="col" class="filterable">Dönem</th>
                                        <th scope="col" class="filterable">Yıl</th>
                                        <th scope="col" class="filterable">Bina</th>
                                        <th scope="col">Derslik türü</th>

                                        <th scope="col" class="text-center no-export">İşlemler</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td class="text-center no-export">
                                                <input type="checkbox" class="form-check-input bulk-select-row" data-id="<?= $lesson->id ?>">
                                            </td>
                                            <td><?= $lesson->id ?></td>
                                            <td><?= $lesson->code . ($lesson->group_no > 0 ? '.' . $lesson->group_no : '') ?></td>
                                            <td
                                                <?= $lesson->parentLesson ? 'data-bs-toggle="popover" data-bs-trigger="hover" title="Bağlı Ders" data-bs-content="'.$lesson->parentLesson->getFullName(addCode: true, addProgram: true).' Dersine bağlı"' : '' ?>
                                            >
                                                <a href="/admin/lesson/<?= $lesson->id ?>" class="text-dark" title="Görüntüle">
                                                    <?= $lesson->parentLesson ? $lesson->name . "*" : $lesson->name ?>
                                                </a>
                                            </td>
                                            <td><?= $lesson->getTypeName() ?></td>
                                            <td><?= $lesson->size ?></td>
                                            <td><?= $lesson->hours ?></td>
                                            <td><?= $lesson->semester_no ?></td>
                                            <td><?= $lesson->lecturer?->getFullName() ?? '<span class="text-danger">Atanmamış</span>' ?></td>
                                            <td><?= $lesson->department?->name ?? '<span class="text-danger">—</span>' ?></td>
                                            <td><?= $lesson->program?->name ?? '<span class="text-danger">—</span>' ?></td>
                                            <td><?= $lesson->semester ?? '<span class="text-danger">—</span>' ?></td>
                                            <td><?= $lesson->academic_year ?? '<span class="text-danger">—</span>' ?></td>
                                            <td><?= $lesson->building?->name ?? '<span class="text-danger">—</span>' ?></td>
                                            <td><?= $lesson->getClassroomTypeName() ?></td>


                                            <td class="text-center">
                                                <?php if (Gate::check("update", $lesson)): ?>
                                                <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (Gate::check("delete", $lesson)): ?>
                                                <form action="/ajax/deletelesson/<?= $lesson->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteLesson-<?= $lesson->id ?>"
                                                      method="post">
                                                    <input type="hidden" name="id" value="<?= $lesson->id ?>">
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
        entity: 'lesson',
        deleteUrl: '/ajax/bulkDeleteLessons',
        updateUrl: '/ajax/bulkUpdateLessons',
        deleteConfirmMessage: 'Seçili dersleri silmek istediğinize emin misiniz? Bu işlem ders programındaki tüm ilişkili kayıtları da temizleyecektir.',
        editableFields: [
            { name: 'semester', label: 'Dönem', type: 'select', options: [
                { value: 'Güz', label: 'Güz' },
                { value: 'Bahar', label: 'Bahar' },
                { value: 'Yaz', label: 'Yaz' }
            ] },
            { name: 'academic_year', label: 'Akademik Yıl (örn: 2025-2026)', type: 'text' },
            { name: 'building_id', label: 'Bina', type: 'select', options: <?= json_encode($buildingOptions ?? []) ?> },
            { name: 'lecturer_id', label: 'Öğretim Elemanı', type: 'select', options: <?= json_encode($lecturerOptions ?? []) ?> }
        ]
    });
});
</script>