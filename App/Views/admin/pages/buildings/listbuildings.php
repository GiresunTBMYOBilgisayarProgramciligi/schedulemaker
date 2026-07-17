<?php
/**
 * @var string $page_title
 * @var \App\Models\Building[] $buildings
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
                        <li class="breadcrumb-item active">Bina Listesi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Binalar</h3>
                            <div class="card-tools">
                                <a href="/admin/addbuilding" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Bina Ekle
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bina Adı</th>
                                    <th>İşlemler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($buildings as $building): ?>
                                    <tr>
                                        <td><?= $building->id ?></td>
                                        <td><?= htmlspecialchars($building->name) ?></td>
                                        <td class="text-center">
                                            <a href="/admin/building/<?= $building->id ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="/admin/editbuilding/<?= $building->id ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="/ajax/deletebuilding/<?= $building->id ?>"
                                                  class="ajaxFormDelete d-inline"
                                                  id="deleteBuilding-<?= $building->id ?>"
                                                  method="post">
                                                <input type="hidden" name="id" value="<?= $building->id ?>">
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
