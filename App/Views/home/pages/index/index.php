<?php
/**
 * @var string $page_title
 * @var array $units
 * @var array $departments
 * @var array $lecturers
 * @var array $classrooms
 * @var string|int|null $selected_unit_id
 * @var string|int|null $selected_department_id
 * @var string|int|null $selected_program_id
 */

use App\Core\View;
use App\Enums\ExamType;
use function App\Helpers\getSettingValue;

$currentAcademicYear = getSettingValue("academic_year") ?? (date('Y') . ' - ' . (date('Y') + 1));
$currentSemester = getSettingValue("semester") ?? 'Bahar';
?>

<!--begin::Portal Page Container-->
<div class="container-xl py-4 py-lg-5">

    <!--begin::Hero Banner Section-->
    <section class="portal-hero p-4 p-md-5 mb-4 shadow-sm">
        <div class="row align-items-center gy-4">
            <div class="col-12 col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-body border shadow-xs mb-3">
                    <i class="bi bi-shield-check text-success"></i>
                    <span class="small fw-semibold text-muted">Resmi Üniversite Portalı</span>
                    <span class="vr mx-1"></span>
                    <span class="small fw-bold text-primary"><?= htmlspecialchars($currentAcademicYear) ?> <?= htmlspecialchars($currentSemester) ?></span>
                </div>
                <h1 class="display-6 fw-bold tracking-tight text-body mb-3">
                    Haftalık Ders ve Sınav Programı Portalı
                </h1>
                <p class="lead text-muted fs-6 mb-4" style="max-width: 650px;">
                    Giresun Üniversitesi bünyesindeki tüm fakülte, yüksekokul ve meslek yüksekokullarına ait güncel haftalık ders çizelgelerini, sınav takvimlerini ve derslik doluluklarını anında sorgulayın.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2">
                        <i class="bi bi-phone text-primary"></i> Mobil & Tablet Uyumlu
                    </span>
                    <span class="badge text-bg-light border px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2">
                        <i class="bi bi-calendar-event text-success"></i> Takvim (iCal) Entegrasyonu
                    </span>
                    <span class="badge text-bg-light border px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-excel text-success"></i> Excel İndirme
                    </span>
                </div>
            </div>
            <div class="col-12 col-lg-4 text-center d-none d-lg-block">
                <div class="position-relative d-inline-block">
                    <div class="bg-body rounded-4 p-3 shadow-sm border border-primary-subtle d-inline-flex align-items-center justify-content-center" style="width: 160px; height: 160px;">
                        <img src="/assets/images/gru_logo.png" alt="Giresun Üniversitesi Logo" width="130" height="130" class="img-fluid" />
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--end::Hero Banner Section-->

    <!--begin::Program Filter & Query Studio Card-->
    <section class="card portal-card mb-4 bg-body">
        <div class="card-body p-4 p-md-5">

            <!--begin::Step 1: Program Türü & Dönem Seçimi Barı-->
            <div class="row align-items-center gy-3 mb-4 pb-3 border-bottom">
                <div class="col-12 col-lg-7">
                    <label class="form-label fw-bold small text-uppercase text-muted tracking-wider mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-layers-fill text-primary"></i> 1. Program Türü Seçimi
                    </label>
                    <!-- Visual Segmented Button Pills -->
                    <div class="schedule-type-btn-group" id="scheduleTypePills" role="group" aria-label="Program Türü">
                        <button type="button" class="schedule-type-btn active" data-type="lesson">
                            <i class="bi bi-book-half"></i>
                            <span>Ders Programı</span>
                        </button>
                        <button type="button" class="schedule-type-btn" data-type="<?= ExamType::MIDTERM->value ?>">
                            <i class="bi bi-pencil-square"></i>
                            <span>Ara Sınav</span>
                        </button>
                        <button type="button" class="schedule-type-btn" data-type="<?= ExamType::FINAL->value ?>">
                            <i class="bi bi-mortarboard"></i>
                            <span>Final</span>
                        </button>
                        <button type="button" class="schedule-type-btn" data-type="<?= ExamType::MAKEUP->value ?>">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Bütünleme</span>
                        </button>
                    </div>

                    <!-- Hidden/Synchronized Select for Native JS Compatibility -->
                    <select class="d-none" id="schedule_type" name="schedule_type">
                        <option value="lesson" selected>Ders Programı</option>
                        <option value="<?= ExamType::MIDTERM->value ?>">Ara Sınav Programı</option>
                        <option value="<?= ExamType::FINAL->value ?>">Final Programı</option>
                        <option value="<?= ExamType::MAKEUP->value ?>">Bütünleme Programı</option>
                    </select>
                </div>

                <!-- Academic Year and Semester Controls -->
                <div class="col-12 col-lg-5">
                    <label class="form-label fw-bold small text-uppercase text-muted tracking-wider mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3 text-primary"></i> 2. Akademik Dönem
                    </label>
                    <div class="input-group shadow-xs">
                        <span class="input-group-text bg-body-secondary text-muted"><i class="bi bi-calendar-range"></i></span>
                        <select class="form-select" id="academic_year" name="academic_year" aria-label="Akademik Yıl">
                            <?php for ($year = 2023; $year <= (int)date('Y'); $year++): ?>
                                <option value="<?= $year . ' - ' . ($year + 1) ?>"
                                    <?= $currentAcademicYear == ($year . ' - ' . ($year + 1)) ? 'selected' : '' ?>>
                                    <?= $year . ' - ' . ($year + 1) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select class="form-select" id="semester" name="semester" aria-label="Dönem">
                            <option value="Güz" <?= $currentSemester == 'Güz' ? 'selected' : '' ?>>Güz</option>
                            <option value="Bahar" <?= $currentSemester == 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                            <option value="Yaz" <?= $currentSemester == 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                        </select>
                    </div>
                </div>
            </div>
            <!--end::Step 1-->

            <!--begin::Step 2: Sorgulama Modu Sekmeleri-->
            <div class="mb-4">
                <label class="form-label fw-bold small text-uppercase text-muted tracking-wider mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-search text-primary"></i> 3. Arama Kriteri Belirleyin
                </label>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs nav-tabs-portal flex-nowrap overflow-x-auto" id="scheduleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active d-inline-flex align-items-center gap-2" id="program-tab" data-bs-toggle="tab"
                            data-bs-target="#program-tab-pane" type="button" role="tab"
                            aria-controls="program-tab-pane" aria-selected="true">
                            <i class="bi bi-mortarboard-fill text-primary"></i>
                            <span>Birim / Bölüm / Program</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-inline-flex align-items-center gap-2" id="lecturer-tab" data-bs-toggle="tab"
                            data-bs-target="#lecturer-tab-pane" type="button" role="tab"
                            aria-controls="lecturer-tab-pane" aria-selected="false">
                            <i class="bi bi-person-workspace text-success"></i>
                            <span>Öğretim Elemanı</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-inline-flex align-items-center gap-2" id="classroom-tab" data-bs-toggle="tab"
                            data-bs-target="#classroom-tab-pane" type="button" role="tab"
                            aria-controls="classroom-tab-pane" aria-selected="false">
                            <i class="bi bi-door-open-fill text-warning"></i>
                            <span>Derslik & Amfi</span>
                        </button>
                    </li>
                </ul>
            </div>
            <!--end::Step 2-->

            <!--begin::Tabs Form Content-->
            <div class="tab-content pt-2" id="scheduleTabsContent">
                <!-- Tab 1: Birim / Bölüm / Program (Öğrenci Odaklı) -->
                <div class="tab-pane fade show active" id="program-tab-pane" role="tabpanel" aria-labelledby="program-tab" tabindex="0">
                    <div class="p-3 p-md-4 rounded-4 bg-body-tertiary border">
                        <div class="row align-items-end gy-3">
                            <div class="col-12">
                                <?= View::renderComponent('schedules/_programSelector', [
                                    'units' => $units ?? [],
                                    'selectedUnitId' => $selected_unit_id ?? '',
                                    'selectedDepartmentId' => $selected_department_id ?? '',
                                    'selectedProgramId' => $selected_program_id ?? '',
                                    'dataAction' => 'public',
                                    'buttonId' => 'departmentAndProgramScheduleButton',
                                    'buttonText' => 'Programı Göster',
                                    'dataOnlyTable' => 'true',
                                    'customButtonHtml' => '<button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="departmentAndProgramScheduleButton" data-only-table="true"><i class="bi bi-calendar-check"></i><span>Programı Göster</span></button>'
                                ]) ?>
                            </div>
                        </div>
                        <div class="form-text mt-2 text-muted small d-flex align-items-center gap-1">
                            <i class="bi bi-info-circle"></i>
                            <span>Fakülte/MYO, Bölüm ve Programınızı sırayla seçerek ders veya sınav çizelgenizi listeleyebilirsiniz.</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Hoca / Öğretim Görevlisi -->
                <div class="tab-pane fade" id="lecturer-tab-pane" role="tabpanel" aria-labelledby="lecturer-tab" tabindex="0">
                    <div class="p-3 p-md-4 rounded-4 bg-body-tertiary border">
                        <div class="row gy-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-semibold text-muted mb-1">Akademik Birim</label>
                                <select class="form-select tom-select" id="lecturer_unit_id" name="lecturer_unit_id" data-action="public">
                                    <option value="">Birim Seçiniz</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label small fw-semibold text-muted mb-1">Öğretim Elemanı</label>
                                <div class="input-group">
                                    <select class="form-select tom-select" id="lecturer_id" name="lecturer_id" placeholder="Öğretim Üyesi / Görevlisi Seçiniz">
                                        <option value="0">İlk olarak Birim Seçiniz</option>
                                    </select>
                                    <button class="btn btn-primary px-4 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" type="button" id="lecturerScheduleButton" data-only-table="true">
                                        <i class="bi bi-calendar-check"></i>
                                        <span>Programı Göster</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2 text-muted small d-flex align-items-center gap-1">
                            <i class="bi bi-info-circle"></i>
                            <span>Öğretim elemanının haftalık ders yükünü veya sınav gözetmenlik programını görüntüleyin.</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Derslik / Amfi / Laboratuvar -->
                <div class="tab-pane fade" id="classroom-tab-pane" role="tabpanel" aria-labelledby="classroom-tab" tabindex="0">
                    <div class="p-3 p-md-4 rounded-4 bg-body-tertiary border">
                        <div class="row gy-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted mb-1">Akademik Birim</label>
                                <select class="form-select tom-select" id="classroom_unit_id" name="classroom_unit_id" data-action="public">
                                    <option value="">Birim Seçiniz</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted mb-1">Bina / Blok</label>
                                <select class="form-select tom-select" id="classroom_building_id" name="classroom_building_id" data-action="public">
                                    <option value="0">İlk olarak Birim Seçiniz</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted mb-1">Derslik / Salon</label>
                                <div class="input-group">
                                    <select class="form-select" id="classroom_id" name="classroom_id">
                                        <option value="0">İlk olarak Bina Seçiniz</option>
                                    </select>
                                    <button type="button" class="btn btn-primary px-3 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="classroomScheduleButton" data-only-table="true">
                                        <i class="bi bi-calendar-check"></i>
                                        <span>Göster</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2 text-muted small d-flex align-items-center gap-1">
                            <i class="bi bi-info-circle"></i>
                            <span>Derslik, amfi ve laboratuvarların haftalık doluluk çizelgesini inceleyin.</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Tabs Form Content-->

        </div>
    </section>
    <!--end::Program Filter & Query Studio Card-->

    <!--begin::Schedule Display Stage-->
    <section class="schedule-result-section mb-5">
        <div id="schedule_container">
            <!-- Initial Empty State Placeholder -->
            <div class="empty-state-container shadow-xs">
                <div class="empty-state-icon-wrapper">
                    <i class="bi bi-calendar-range"></i>
                </div>
                <h3 class="fw-bold text-body mb-2 fs-4">Görüntülenecek Programı Seçiniz</h3>
                <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
                    Yukarıdaki arama stüdyosundan birim/bölüm, öğretim elemanı veya derslik seçimi yaptıktan sonra 
                    <strong class="text-body">Programı Göster</strong> butonuna tıklayarak haftalık çizelgenizi anında listeleyebilirsiniz.
                </p>
                <div class="d-inline-flex flex-wrap justify-content-center gap-2">
                    <span class="badge bg-body-secondary text-secondary border px-3 py-2 rounded-pill">
                        💡 Sınav programlarında hafta butonlarını kullanarak tüm tarihleri görebilirsiniz
                    </span>
                    <span class="badge bg-body-secondary text-secondary border px-3 py-2 rounded-pill">
                        📲 'Takvime Kaydet' ile telefonunuza aktarabilirsiniz
                    </span>
                </div>
            </div>
        </div>
    </section>
    <!--end::Schedule Display Stage-->

    <!--begin::Feature Highlights Grid-->
    <section class="feature-highlights-section mb-4">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="card feature-card h-100 p-4">
                    <div class="feature-icon-box bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <h5 class="fw-bold text-body mb-2">Hızlı & Zahmetsiz Erişim</h5>
                    <p class="text-muted small mb-0">
                        Öğrenciler ve öğretim elemanları için geliştirilmiş optimize edilmiş arama stüdyosu ile haftalık programa saniyeler içinde ulaşın.
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card feature-card h-100 p-4">
                    <div class="feature-icon-box bg-success-subtle text-success mb-3">
                        <i class="bi bi-phone-fill"></i>
                    </div>
                    <h5 class="fw-bold text-body mb-2">Telefon Takviminize Ekleyin</h5>
                    <p class="text-muted small mb-0">
                        iCal/ICS desteği sayesinde Google Calendar, Apple Takvim veya Outlook'a tek tıkla entegre edin, ders ve sınav saatlerinizi kaçırmayın.
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card feature-card h-100 p-4">
                    <div class="feature-icon-box bg-warning-subtle text-warning mb-3">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <h5 class="fw-bold text-body mb-2">Canlı & Güncel Bilgi</h5>
                    <p class="text-muted small mb-0">
                        Bölüm başkanlıkları ve idare tarafından yapılan tüm derslik ve saat güncellemeleri anında sisteme yansıtılır.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <!--end::Feature Highlights Grid-->

</div>
<!--end::Portal Page Container-->

<!--begin::Inline Script for Segmented Pills & Smooth Scroll-->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const pills = document.querySelectorAll("#scheduleTypePills .schedule-type-btn");
    const scheduleTypeSelect = document.getElementById("schedule_type");

    if (pills.length && scheduleTypeSelect) {
        pills.forEach(btn => {
            btn.addEventListener("click", function () {
                pills.forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                const selectedType = this.dataset.type;
                scheduleTypeSelect.value = selectedType;
                scheduleTypeSelect.dispatchEvent(new Event("change"));
            });
        });
    }

    // Program yüklendiğinde sonuç sahnesine yumuşak kaydırma
    document.addEventListener("scheduleLoaded", function () {
        const container = document.getElementById("schedule_container");
        if (container) {
            container.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    });
});
</script>
<!--end::Inline Script-->