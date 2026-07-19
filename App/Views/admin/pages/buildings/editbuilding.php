<?php
/**
 * @var string $page_title
 * @var \App\Models\Building $building
 * @var \App\Models\Unit[] $units
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
                        <li class="breadcrumb-item active">Düzenle</li>
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
                        <form action="/ajax/updateBuilding" method="post" class="ajaxForm" title="Binayı Güncelle">
                            <input type="hidden" name="id" value="<?= $building->id ?>">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Bina Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?= htmlspecialchars($building->name ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="unit_id">Bağlı Birim</label>
                                            <select class="form-select" id="unit_id" name="unit_id" required>
                                                <option value="">Seçiniz</option>
                                                <?php foreach ($units as $unit): ?>
                                                    <option value="<?= $unit->id ?>" <?= ($building->unit_id == $unit->id) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($unit->name) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="/admin/listbuildings" class="btn btn-secondary me-2">İptal</a>
                                <button type="submit" class="btn btn-primary">Güncelle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
