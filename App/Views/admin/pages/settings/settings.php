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
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= $page_title ?></h3>
                </div>
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
                                <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab"
                                            data-bs-target="#general" type="button" role="tab" aria-controls="general"
                                            aria-selected="true">Genel Ayarlar</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="lesson-tab" data-bs-toggle="tab"
                                            data-bs-target="#lesson" type="button" role="tab" aria-controls="lesson"
                                            aria-selected="false">Ders Ayarları</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="exam-tab" data-bs-toggle="tab"
                                            data-bs-target="#exam" type="button" role="tab" aria-controls="exam"
                                            aria-selected="false">Sınav Ayarları</button>
                                    </li>
                                </ul>
                                <div class="tab-content pt-3" id="settingsTabContent">
                                    <!-- General Settings Tab -->
                                    <div class="tab-pane fade show active" id="general" role="tabpanel"
                                        aria-labelledby="general-tab">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[general][academic_year][value]">Dönem</label>
                                                <input type="hidden" name="settings[general][academic_year][type]"
                                                    id="settings[general][academic_year][type]" value="string">
                                                <div class="input-group ">
                                                    <select class="form-select"
                                                        id="settings[general][academic_year][value]"
                                                        name="settings[general][academic_year][value]">
                                                        <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                                            <option value="<?= $year . ' - ' . $year + 1 ?>"
                                                                <?= $settings['general']["academic_year"] == $year . ' - ' . $year + 1 ? 'selected' : '' ?>>
                                                                <?= $year . ' - ' . $year + 1 ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <span class="input-group-text"> - </span>
                                                    <input type="hidden" name="settings[general][semester][type]"
                                                        id="settings[general][semester][type]" value="string">
                                                    <select class="form-select" id="settings[general][semester][value]"
                                                        name="settings[general][semester][value]">
                                                        <option value="Güz" <?= $settings['general']["semester"] == 'Güz' ? 'selected' : '' ?>>Güz</option>
                                                        <option value="Bahar"
                                                            <?= $settings['general']["semester"] == 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                                                        <option value="Yaz" <?= $settings['general']["semester"] == 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Lesson Settings Tab -->
                                    <div class="tab-pane fade" id="lesson" role="tabpanel" aria-labelledby="lesson-tab">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[lesson][maxDayIndex][value]">Ders Programının Son
                                                    Günü</label>
                                                <input type="hidden" name="settings[lesson][maxDayIndex][type]"
                                                    id="settings[lesson][maxDayIndex][type]" value="integer">
                                                <select class="form-select" id="settings[lesson][maxDayIndex][value]"
                                                    name="settings[lesson][maxDayIndex][value]">
                                                    <?php $maxDay = isset($settings['lesson']["maxDayIndex"]) ? (int) $settings['lesson']["maxDayIndex"] : 4; ?>
                                                    <option value="0" <?= $maxDay == 0 ? 'selected' : '' ?>>Pazartesi
                                                    </option>
                                                    <option value="1" <?= $maxDay == 1 ? 'selected' : '' ?>>Salı</option>
                                                    <option value="2" <?= $maxDay == 2 ? 'selected' : '' ?>>Çarşamba
                                                    </option>
                                                    <option value="3" <?= $maxDay == 3 ? 'selected' : '' ?>>Perşembe
                                                    </option>
                                                    <option value="4" <?= $maxDay == 4 ? 'selected' : '' ?>>Cuma</option>
                                                    <option value="5" <?= $maxDay == 5 ? 'selected' : '' ?>>Cumartesi
                                                    </option>
                                                    <option value="6" <?= $maxDay == 6 ? 'selected' : '' ?>>Pazar</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[lesson][lesson_start_date][value]">Derslerin başlangıç
                                                    tarihi</label>
                                                <input type="hidden" name="settings[lesson][lesson_start_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[lesson][lesson_start_date][value]"
                                                    name="settings[lesson][lesson_start_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['lesson']['lesson_start_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[lesson][lesson_end_date][value]">Derslerin bitiş
                                                    tarihi</label>
                                                <input type="hidden" name="settings[lesson][lesson_end_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[lesson][lesson_end_date][value]"
                                                    name="settings[lesson][lesson_end_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['lesson']['lesson_end_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Exam Settings Tab -->
                                    <div class="tab-pane fade" id="exam" role="tabpanel" aria-labelledby="exam-tab">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][midterm_start_date][value]">Vize başlangıç
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][midterm_start_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][midterm_start_date][value]"
                                                    name="settings[exam][midterm_start_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['midterm_start_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][midterm_end_date][value]">Vize bitiş
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][midterm_end_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][midterm_end_date][value]"
                                                    name="settings[exam][midterm_end_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['midterm_end_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][final_start_date][value]">Final başlangıç
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][final_start_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][final_start_date][value]"
                                                    name="settings[exam][final_start_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['final_start_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][final_end_date][value]">Final bitiş
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][final_end_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][final_end_date][value]"
                                                    name="settings[exam][final_end_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['final_end_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][makeup_start_date][value]">Bütünleme başlangıç
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][makeup_start_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][makeup_start_date][value]"
                                                    name="settings[exam][makeup_start_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['makeup_start_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][makeup_end_date][value]">Bütünleme bitiş
                                                    tarihi</label>
                                                <input type="hidden" name="settings[exam][makeup_end_date][type]"
                                                    value="string">
                                                <input type="date" class="form-control"
                                                    id="settings[exam][makeup_end_date][value]"
                                                    name="settings[exam][makeup_end_date][value]"
                                                    value="<?= htmlspecialchars(@$settings['exam']['makeup_end_date'] ?? '') ?>">
                                                <div class="form-text">Gün/Ay/Yıl olarak seçiniz.</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="col-form-label"
                                                    for="settings[exam][maxExamDayIndex][value]">Sınav Programının Son
                                                    Günü</label>
                                                <input type="hidden" name="settings[exam][maxExamDayIndex][type]"
                                                    id="settings[exam][maxExamDayIndex][type]" value="integer">
                                                <select class="form-select" id="settings[exam][maxExamDayIndex][value]"
                                                    name="settings[exam][maxExamDayIndex][value]">
                                                    <?php $maxExam = isset($settings['exam']["maxExamDayIndex"]) ? (int) $settings['exam']["maxExamDayIndex"] : 5; ?>
                                                    <option value="0" <?= ($maxExam === 0) ? 'selected' : '' ?>>Pazartesi
                                                    </option>
                                                    <option value="1" <?= ($maxExam === 1) ? 'selected' : '' ?>>Salı
                                                    </option>
                                                    <option value="2" <?= ($maxExam === 2) ? 'selected' : '' ?>>Çarşamba
                                                    </option>
                                                    <option value="3" <?= ($maxExam === 3) ? 'selected' : '' ?>>Perşembe
                                                    </option>
                                                    <option value="4" <?= ($maxExam === 4) ? 'selected' : '' ?>>Cuma
                                                    </option>
                                                    <option value="5" <?= ($maxExam === 5) ? 'selected' : '' ?>>Cumartesi
                                                    </option>
                                                    <option value="6" <?= ($maxExam === 6) ? 'selected' : '' ?>>Pazar
                                                    </option>
                                                </select>
                                                <div class="form-text">Sınav takviminde haftanın dahil edileceği son
                                                    günü belirler. Varsayılan: Cumartesi.</div>
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