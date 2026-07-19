<?php
/**
 * @var string $page_title
 * @var \App\Models\Building $building
 */
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
                        <li class="breadcrumb-item"><a href="/admin/listbuildings">Bina Listesi</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($building->name ?? '') ?></li>
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
                            <h3 class="card-title"><?= htmlspecialchars($building->name ?? '') ?> (Bağlı Birim: <?= htmlspecialchars($building->unit->name ?? 'Yok') ?>)</h3>
                            <div class="card-tools">
                                <a href="/admin/editbuilding/<?= $building->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="bi bi-pencil"></i> Düzenle
                                </a>
                                <form action="/ajax/deletebuilding/<?= $building->id ?>"
                                      class="ajaxFormDelete d-inline"
                                      id="deleteBuilding-<?= $building->id ?>"
                                      method="post">
                                    <input type="hidden" name="id" value="<?= $building->id ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Bağlı Derslikler -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Binadaki Derslikler</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Derslik Adı</th>
                                    <th>Türü</th>
                                    <th>Kapasite</th>
                                    <th class="text-center">İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($building->classrooms)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">Bu binada derslik bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($building->classrooms as $cls): ?>
                                        <tr>
                                            <td><?= $cls->id ?></td>
                                            <td><a href="/admin/classroom/<?= $cls->id ?>" class="text-dark" title="Görüntüle"><?= htmlspecialchars($cls->name ?? '') ?></a></td>
                                            <td><?= $cls->getTypeName() ?></td>
                                            <td><?= $cls->class_size ?></td>
                                            <td class="text-center">
                                                <a href="/admin/editclassroom/<?= $cls->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="/ajax/deleteclassroom/<?= $cls->id ?>"
                                                      class="ajaxFormDelete d-inline"
                                                      id="deleteClassroom-<?= $cls->id ?>"
                                                      method="post">
                                                    <input type="hidden" name="id" value="<?= $cls->id ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
