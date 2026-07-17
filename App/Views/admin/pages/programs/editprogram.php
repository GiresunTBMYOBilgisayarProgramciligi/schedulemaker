<?php
/**
 * @var array $departments
 * @var \App\Models\Department $department
 * @var \App\Models\Program $program
 * @var string $page_title
 */
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
                        <li class="breadcrumb-item">Program İşlemleri</li>
                        <li class="breadcrumb-item active">Düzenle</li>
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
                        <form action="/ajax/updateprogram" method="post" class="ajaxForm updateForm"
                              title="Program Bilgilerini Güncelle">
                            <input type="hidden" name="id" value="<?= $program->id ?>">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label" for="unit_id">Üst Birim</label>
                                            <select class="form-select tom-select" id="unit_id" name="unit_id" required>
                                                <option value="">Birim Seçiniz</option>
                                                <?php foreach ($units as $unit): ?>
                                                    <option value="<?= $unit->id ?>" <?= ($program->department->unit_id ?? '') == $unit->id ? 'selected' : '' ?>><?= htmlspecialchars($unit->name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label" for="department_id">Bölüm</label>
                                            <select class="form-select tom-select" id="department_id" name="department_id" data-selected="<?= $program->department_id ?? '' ?>">
                                                <option value="0">İlk olarak Birim Seçiniz</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı" value="<?= $program->name ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="mb-3">
                                            <span class="form-label d-block">&nbsp;</span>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" id="flexSwitchCheckChecked" <?= $program->active ? "checked" : "" ?>>
                                                <label class="form-check-label" for="flexSwitchCheckChecked">Aktif</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Güncelle</button>
                            </div>
                        </form>
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
