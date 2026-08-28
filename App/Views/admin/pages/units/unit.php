<?php
/**
 * @var string $page_title
 * @var \App\Models\Unit $unit
 */
use App\Core\Gate;
?>
<!--begin::App Main-->
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item"><a href="/admin/listunits">Birim Listesi</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($unit->name ?? '') ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= htmlspecialchars($unit->name ?? '') ?> <small class="text-muted">(<?= $unit->getTypeName() ?>)</small></h3>
                            <div class="card-tools">
                                <?php if (Gate::check("update", $unit)): ?>
                                <a href="/admin/editunit/<?= $unit->id ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-pencil"></i> Düzenle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-2"><strong><?= $unit->getManagerTitle() ?>:</strong> <?= $unit->manager ? htmlspecialchars($unit->manager->getFullName()) : '<span class="text-muted fst-italic">Atanmamış</span>' ?></p>
                                </div>
                                <div class="col-md-5">
                                    <p class="mb-2">
                                        <strong><?= $unit->getSubManagerTitle(count($unit->submanagers) > 1) ?>:</strong>
                                        <?php if (!empty($unit->submanagers)): ?>
                                            <?= implode(', ', array_map(fn($sm) => htmlspecialchars($sm->getFullName()), $unit->submanagers)) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Atanmamış</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-0"><strong>Durum:</strong>
                                        <?php if ($unit->active): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Bağlı Bölümler -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Bağlı Bölümler</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bölüm Adı</th>
                                    <th>Bölüm Başkanı</th>
                                    <th class="text-center">İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($unit->departments)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Bu birime bağlı bölüm bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($unit->departments as $dept): ?>
                                        <tr>
                                            <td><?= $dept->id ?></td>
                                            <td><a href="/admin/department/<?= $dept->id ?>" class="text-dark" title="Görüntüle"><?= htmlspecialchars($dept->name ?? '') ?></a></td>
                                            <td><?= $dept->chairperson?->getFullName() ?? '-' ?></td>
                                            <td class="text-center">
                                                <?php if (Gate::check("update", $dept)): ?>
                                                <a href="/admin/editdepartment/<?= $dept->id ?>" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (Gate::check("delete", $dept)): ?>
                                                <form action="/ajax/deletedepartment/<?= $dept->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteDepartment-<?= $dept->id ?>"
                                                      method="post"
                                                      data-confirm-message="Bölümü sildiğinizde altındaki tüm programlar ve bu programlara ait dersler de silinecektir. Devam etmek istiyor musunuz?">
                                                    <input type="hidden" name="id" value="<?= $dept->id ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
