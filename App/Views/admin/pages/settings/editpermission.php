<?php
/**
 * @var string $page_title
 * @var array $users
 * @var \App\Models\Unit[] $units
 */
use App\Enums\PermissionType;
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
                        <li class="breadcrumb-item">Ayarlar</li>
                        <li class="breadcrumb-item active">Yetkileri Düzenle</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Kullanıcı Yetki Sihirbazı</h3>
                        </div>
                        <div class="card-body">
                            <!-- Stepper Header -->
                            <div class="bs-stepper">
                                <div class="bs-stepper-header" role="tablist">
                                    <div class="step" data-target="#step-user">
                                        <button type="button" class="step-trigger" role="tab" aria-controls="step-user" id="step-user-trigger">
                                            <span class="bs-stepper-circle">1</span>
                                            <span class="bs-stepper-label">Kullanıcı Seçimi</span>
                                        </button>
                                    </div>
                                    <div class="line"></div>
                                    <div class="step" data-target="#step-target">
                                        <button type="button" class="step-trigger" role="tab" aria-controls="step-target" id="step-target-trigger" disabled>
                                            <span class="bs-stepper-circle">2</span>
                                            <span class="bs-stepper-label">Hedef Birim/Bölüm/Program</span>
                                        </button>
                                    </div>
                                    <div class="line"></div>
                                    <div class="step" data-target="#step-permissions">
                                        <button type="button" class="step-trigger" role="tab" aria-controls="step-permissions" id="step-permissions-trigger" disabled>
                                            <span class="bs-stepper-circle">3</span>
                                            <span class="bs-stepper-label">Yetki Seçimi</span>
                                        </button>
                                    </div>
                                    <div class="line"></div>
                                    <div class="step" data-target="#step-summary">
                                        <button type="button" class="step-trigger" role="tab" aria-controls="step-summary" id="step-summary-trigger" disabled>
                                            <span class="bs-stepper-circle">4</span>
                                            <span class="bs-stepper-label">Özet ve Kaydet</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="bs-stepper-content mt-4">
                                    <!-- Step 1: User -->
                                    <div id="step-user" class="content" role="tabpanel" aria-labelledby="step-user-trigger">
                                        <div class="form-group mb-3">
                                            <label for="wizard_user_id">Kullanıcı Seçiniz</label>
                                            <select class="form-select tom-select" id="wizard_user_id" name="user_id">
                                                <option value=""></option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?= $user->id ?>"><?= $user->getFullName() ?> (<?= $user->mail ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-next" onclick="wizardNext(2)">İleri</button>
                                    </div>

                                    <!-- Step 2: Target -->
                                    <div id="step-target" class="content d-none" role="tabpanel" aria-labelledby="step-target-trigger">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label" for="unit_id">Üst Birim</label>
                                                    <select class="form-select tom-select" id="unit_id" name="unit_id">
                                                        <option value="">Birim Seçiniz</option>
                                                        <?php foreach ($units as $unit): ?>
                                                            <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label" for="department_id">Bölüm</label>
                                                    <select class="form-select tom-select" id="department_id" name="department_id">
                                                        <option value="0">İlk olarak Birim Seçiniz</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label" for="program_id">Program</label>
                                                    <select class="form-select" id="program_id" name="program_id">
                                                        <option value="0">İlk olarak Bölüm Seçiniz</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-muted"><small>Not: Sadece birim seçilirse yetki, birim ve altındaki tüm bölümleri/programları kapsar. Bölüm seçilirse ilgili bölüm ve programlarını kapsar. Program seçilirse sadece seçilen programı kapsar.</small></p>
                                        <button type="button" class="btn btn-secondary btn-prev" onclick="wizardPrev(1)">Geri</button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="wizardNext(3)">İleri</button>
                                    </div>

                                    <!-- Step 3: Permissions -->
                                    <div id="step-permissions" class="content d-none" role="tabpanel" aria-labelledby="step-permissions-trigger">
                                        <div class="form-group mb-3">
                                            <label>Verilecek Yetkiler</label>
                                            <div class="row">
                                                <?php foreach (PermissionType::getManageablePermissions() as $perm): ?>
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input permission-checkbox" type="checkbox" value="<?= $perm->value ?>" id="perm_<?= $perm->value ?>">
                                                            <label class="form-check-label" for="perm_<?= $perm->value ?>">
                                                                <?= $perm->getLabel() ?> (<?= $perm->value ?>)
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-prev" onclick="wizardPrev(2)">Geri</button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="wizardNext(4)">İleri (Özet)</button>
                                    </div>

                                    <!-- Step 4: Summary -->
                                    <div id="step-summary" class="content d-none" role="tabpanel" aria-labelledby="step-summary-trigger">
                                        <div class="alert alert-info">
                                            <h5>Özet Bilgiler</h5>
                                            <p><strong>Kullanıcı:</strong> <span id="summary-user"></span></p>
                                            <p><strong>Hedef Kapsam:</strong> <span id="summary-target"></span></p>
                                            <p><strong>Yetkiler:</strong></p>
                                            <ul id="summary-permissions"></ul>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-prev" onclick="wizardPrev(3)">Geri</button>
                                        <button type="button" class="btn btn-success" id="btnSavePermissions" onclick="savePermissions()">Yetkileri Kaydet</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->

<style>
/* Basit Wizard Stilleri (Eğer AdminLTE BS Stepper yoksa fallback olarak) */
.bs-stepper-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.bs-stepper-header .step {
    text-align: center;
}
.bs-stepper-header .line {
    flex: 1;
    height: 2px;
    background-color: #dee2e6;
    margin: 0 15px;
}
.step-trigger {
    background: none;
    border: none;
    padding: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #6c757d;
}
.step-trigger:not(:disabled) {
    color: #0d6efd;
    cursor: pointer;
}
.bs-stepper-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    margin-bottom: 5px;
}
.step-trigger:not(:disabled) .bs-stepper-circle {
    background-color: #0d6efd;
}
.step-trigger.active {
    font-weight: bold;
}
.step-trigger.active .bs-stepper-circle {
    background-color: #198754;
}
</style>
