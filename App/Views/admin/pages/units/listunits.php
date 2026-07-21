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
                                <a href="/admin/addunit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Birim Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ad</th>
                                    <th>Tür</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($units as $unit): ?>
                                    <tr>
                                        <td><?= $unit->id ?></td>
                                        <td><a href="/admin/unit/<?= $unit->id ?>" class="text-dark" title="Görüntüle"><?= htmlspecialchars($unit->name) ?></a></td>
                                        <td><?= $unit->getTypeName() ?></td>
                                        <td>
                                            <?php if ($unit->active): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="/admin/editunit/<?= $unit->id ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="/ajax/deleteunit/<?= $unit->id ?>"
                                                  class="ajaxFormDelete d-inline"
                                                  id="deleteUnit-<?= $unit->id ?>"
                                                  method="post">
                                                <input type="hidden" name="id" value="<?= $unit->id ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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
</main>
<!--end::App Main-->
