<?php
/**
 * @var string $page_title
 * @var array $unitTypes  [['value'=>..., 'label'=>...], ...]
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
                        <li class="breadcrumb-item"><a href="/admin/listunits">Birim Listesi</a></li>
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
                        <form action="/ajax/addUnit" method="post" class="ajaxForm js-reset-on-success" title="Yeni Birim Ekle">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Birim Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Örn: Mühendislik Fakültesi" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="type">Birim Türü</label>
                                            <select class="form-select" id="type" name="type" required>
                                                <option value="">Seçiniz...</option>
                                                <?php foreach ($unitTypes as $t): ?>
                                                    <option value="<?= $t['value'] ?>"><?= $t['label'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check form-switch pt-4 mt-3">
                                            <input name="active" class="form-check-input" type="checkbox" id="active" checked>
                                            <label class="form-check-label" for="active">Aktif</label>
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
