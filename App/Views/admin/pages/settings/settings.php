<?php
/**
 * @var string $page_title
 * @var array $settings
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
                        <li class="breadcrumb-item">Ayarlar</li>
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
                        <form id="addUserForm" action="/ajax/saveSettings" method="post" class="ajaxForm updateForm"
                              title="Ayarları Düzenle">
                            <div class="card-body pb-0">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="col-form-label" for="settings[general][academic_year]">Dönem</label>
                                        <input type="hidden" name="settings[general][academic_year][type]" id="settings[general][academic_year][type]" value="string">
                                        <div class="input-group ">
                                            <select class="form-select" id="settings[general][academic_year][value]"
                                                    name="settings[general][academic_year][value]">
                                                <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                                    <option value="<?= $year . ' - ' . $year + 1 ?>" <?= $settings['general']["academic_year"] == $year . ' - ' . $year + 1 ? 'selected' : '' ?>>
                                                        <?= $year . ' - ' . $year + 1 ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <span class="input-group-text"> - </span>
                                            <input type="hidden" name="settings[general][semester][type]" id="settings[general][semester][type]" value="string">
                                            <select class="form-select" id="settings[general][semester][value]"
                                                    name="settings[general][semester][value]">
                                                <option value="Güz" <?= $settings['general']["semester"] == 'Güz' ? 'selected' : '' ?>>Güz</option>
                                                <option value="Bahar" <?= $settings['general']["semester"] == 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                                                <option value="Yaz" <?= $settings['general']["semester"] == 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Kaydet</button>
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