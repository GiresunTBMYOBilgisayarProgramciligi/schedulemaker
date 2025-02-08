<?php
/**
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
                        <form id="addUserForm" action="/ajax/saveSettings" method="post" class="ajaxForm"
                              title="Ayarları Düzenle">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="col-form-label" for="settings[academic_year]">Dönem</label>
                                            <input type="hidden" name="settings[academic_year][type]" id="settings[academic_year][type]" value="string">
                                            <input type="hidden" name="settings[academic_year][group]" id="settings[academic_year][group]" value="general">
                                            <div class="input-group ">
                                                <select class="form-select" id="settings[academic_year][value]" name="settings[academic_year][value]">
                                                    <option value="2023 - 2024">2023 - 2024</option>
                                                    <option value="2024 - 2025">2024 - 2025</option>
                                                    <option value="2025 - 2026">2025 - 2026</option>
                                                </select>
                                                <span class="input-group-text"> - </span>
                                                <input type="hidden" name="settings[semester][type]" id="settings[semester][type]" value="string">
                                                <input type="hidden" name="settings[semester][group]" id="settings[semester][group]" value="general">
                                                <select class="form-select" id="settings[semester][value]" name="settings[semester][value]">
                                                    <option value="Güz">Güz</option>
                                                    <option value="Bahar">Bahar</option>
                                                    <option value="Yaz">Yaz</option>
                                                </select>
                                            </div>

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