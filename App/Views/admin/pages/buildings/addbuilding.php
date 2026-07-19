<?php
/**
 * @var string $page_title
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
                        <li class="breadcrumb-item active">Ekle</li>
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
                        <form action="/ajax/addBuilding" method="post" class="ajaxForm js-reset-on-success" title="Yeni Bina Ekle">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Bina Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Örn: A Blok" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="unit_id">Bağlı Birim</label>
                                            <select class="form-select" id="unit_id" name="unit_id" required>
                                                <option value="">Seçiniz</option>
                                                <?php foreach ($units as $unit): ?>
                                                    <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
