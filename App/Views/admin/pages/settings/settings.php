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
                                        <label class="col-form-label" for="settings[general][academic_year][value]">Dönem</label>
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
                                    <div class="col-md-6">
                                        <label class="col-form-label" for="settings[general][maxDayIndex][value]">Ders Programının Son Günü</label>
                                        <input type="hidden" name="settings[general][maxDayIndex][type]" id="settings[general][maxDayIndex][type]" value="integer">
                                        <select class="form-select" id="settings[general][maxDayIndex][value]"
                                                name="settings[general][maxDayIndex][value]">
                                            <option value="0" <?= @$settings['general']["maxDayIndex"] == 0 ? 'selected' : '' ?>>Pazartesi</option>
                                            <option value="1" <?= @$settings['general']["maxDayIndex"] == 1 ? 'selected' : '' ?>>Salı</option>
                                            <option value="2" <?= @$settings['general']["maxDayIndex"] == 2 ? 'selected' : '' ?>>Çarşamba</option>
                                            <option value="3" <?= @$settings['general']["maxDayIndex"] == 3 ? 'selected' : '' ?>>Perşembe</option>
                                            <option value="4" <?= @$settings['general']["maxDayIndex"] == 4 ? 'selected' : '' ?>>Cuma</option>
                                            <option value="5" <?= @$settings['general']["maxDayIndex"] == 5 ? 'selected' : '' ?>>Cumartesi</option>
                                            <option value="6" <?= @$settings['general']["maxDayIndex"] == 6 ? 'selected' : '' ?>>Pazar</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="col-form-label" for="settings[general][lesson_start_date][value]">Derslerin başlangıç tarihi</label>
                                        <input type="hidden" name="settings[general][lesson_start_date][type]" value="string">
                                        <input type="date" class="form-control" id="settings[general][lesson_start_date][value]" name="settings[general][lesson_start_date][value]" value="<?= htmlspecialchars(@$settings['general']['lesson_start_date'] ?? '') ?>">
                                        <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="col-form-label" for="settings[general][lesson_end_date][value]">Derslerin bitiş tarihi</label>
                                        <input type="hidden" name="settings[general][lesson_end_date][type]" value="string">
                                        <input type="date" class="form-control" id="settings[general][lesson_end_date][value]" name="settings[general][lesson_end_date][value]" value="<?= htmlspecialchars(@$settings['general']['lesson_end_date'] ?? '') ?>">
                                        <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
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