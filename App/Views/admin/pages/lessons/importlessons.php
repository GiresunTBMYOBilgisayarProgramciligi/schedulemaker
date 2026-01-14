<?php
/**
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var array $departments \App\Models\Department->getDepartments())
 * @var string $page_title
 */

use function App\Helpers\getSettingValue;

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
                        <li class="breadcrumb-item active">İçe Aktar</li>
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
                    <div class="card ">
                        <form id="importUserForm" action="/ajax/importLessons" method="post" class="ajaxForm" enctype="multipart/form-data"
                              title="Ders içe aktar">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-6">
                                    <div class="mb-3">
                                        <label for="importFile" class="col-form-label">Excel Dosyası seç</label>
                                        <input class="form-control" type="file" id="importFile" name="importFile" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                    </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="col-form-label" for="academic_year">Dönem</label>
                                            <div class="input-group ">
                                                <select class="form-select" id="academic_year" name="academic_year">
                                                    <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                                        <option value="<?= $year . ' - ' . $year + 1 ?>" <?= getSettingValue("academic_year") == $year . ' - ' . $year + 1 ? 'selected' : '' ?>>
                                                            <?= $year . ' - ' . $year + 1 ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="input-group-text"> - </span>
                                                <select class="form-select" id="semester" name="semester">
                                                    <option value="Güz" <?= getSettingValue("semester") == 'Güz' ? 'selected' : '' ?>>Güz</option>
                                                    <option value="Bahar" <?= getSettingValue("semester") == 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                                                    <option value="Yaz" <?= getSettingValue("semester") == 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="/admin/download/Ders Listesi.xlsx" type="button" class="btn btn-success">Şablon İndir</a>
                                <button type="submit" class="btn btn-primary">İçe aktar</button>
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
