<?php
/**
 * @var string $page_title
 * @var array $departments
 * @var array $lecturers
 * @var array $classrooms
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
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= $page_title ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Takvim İşlemleri</li>
                        <li class="breadcrumb-item active">Ders Programı Düzenle</li>
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
            <!-- Mobile device warning alert -->
            <div class="alert alert-warning alert-dismissible fade show d-md-none mb-3 shadow-sm border-warning" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-laptop fs-3 me-3 text-warning"></i>
                    <div>
                        <strong>Bilgisayar Kullanımı Tavsiye Edilir</strong>
                        <div class="small">Ders programı düzenleme ve sürükle-bırak işlemleri masaüstü/dizüstü bilgisayarlar için tasarlanmıştır. Mobil cihazlarda takvimi inceleyebilir, ancak düzenlemeleri bilgisayardan yapmanız önerilir.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>

            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline">
                        <!-- .card-header -->
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title flex-fill">Ders Programı Düzenle</h3>
                            <div class="flex-fill">
                                <div class="input-group">
                                    <select class="form-select" id="academic_year" name="academic_year">
                                        <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                            <option value="<?= $year . ' - ' . $year + 1 ?>"
                                                <?= getSettingValue("academic_year") == $year . ' - ' . $year + 1 ? 'selected' : '' ?>>
                                                <?= $year . ' - ' . $year + 1 ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="input-group-text"> - </span>
                                    <select class="form-select" id="semester" name="semester">
                                        <option value="Güz" <?= getSettingValue("semester") == 'Güz' ? 'selected' : '' ?>>
                                            Güz
                                        </option>
                                        <option value="Bahar" <?= getSettingValue("semester") == 'Bahar' ? 'selected' : '' ?>>
                                            Bahar
                                        </option>
                                        <option value="Yaz" <?= getSettingValue("semester") == 'Yaz' ? 'selected' : '' ?>>
                                            Yaz
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-warning" id="btn-show-schedule-notes" title="Hoca Notları & İstekleri">
                                        <i class="bi bi-journal-text me-1"></i> Notlar<span id="schedule-notes-count"></span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" id="btn-bulk-publish">
                                    <i class="bi bi-globe me-1"></i> Tümünü Yayınla
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info text-white" id="btn-notify-changes">
                                        <i class="bi bi-envelope me-1"></i> Değişiklikleri Bildir
                                    </button>
                                </div>
                            </div>
                            <div class="card-tools d-flex gap-2">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <!-- Tabs navs -->
                            <ul class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto" id="scheduleTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="program-tab" data-bs-toggle="tab"
                                        data-bs-target="#program-tab-pane" type="button" role="tab"
                                        aria-controls="program-tab-pane" aria-selected="true">Bölüm/Program</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="lecturer-tab" data-bs-toggle="tab"
                                        data-bs-target="#lecturer-tab-pane" type="button" role="tab"
                                        aria-controls="lecturer-tab-pane" aria-selected="false">Hoca</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="classroom-tab" data-bs-toggle="tab"
                                        data-bs-target="#classroom-tab-pane" type="button" role="tab"
                                        aria-controls="classroom-tab-pane" aria-selected="false">Derslik</button>
                                </li>
                            </ul>

                            <!-- Tabs content -->
                            <div class="tab-content" id="scheduleTabsContent">
                                <div class="tab-pane fade show active" id="program-tab-pane" role="tabpanel"
                                    aria-labelledby="program-tab" tabindex="0">
                                    <!--begin::Row-->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <div class="row">
                                                <div class="col-12 col-md-4">
                                                    <select class="form-select tom-select" id="unit_id" name="unit_id">
                                                        <option value="">Birim Seçiniz</option>
                                                        <?php foreach ($units as $unit): ?>
                                                            <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <select class="form-select tom-select" id="department_id"
                                                        name="department_id">
                                                        <option value="0">İlk olarak Birim Seçiniz</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <div class="input-group">
                                                        <select class="form-select" id="program_id" name="program_id">
                                                            <option value="0">İlk olarak Bölüm seçiniz</option>
                                                        </select>
                                                        <button type="button" class="btn btn-primary"
                                                            id="departmentAndProgramScheduleButton"
                                                            data-only-table="false">
                                                            Düzenle
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="lecturer-tab-pane" role="tabpanel"
                                    aria-labelledby="lecturer-tab" tabindex="0">
                                    <!--begin::Row-->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <div class="row">
                                                <div class="col-12 col-md-6">
                                                    <select class="form-select tom-select" id="lecturer_unit_id" name="lecturer_unit_id">
                                                        <option value="">Birim Seçiniz</option>
                                                        <?php foreach ($units as $unit): ?>
                                                            <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <div class="input-group">
                                                        <select class="form-select tom-select" id="lecturer_id" name="lecturer_id" placeholder="Öğretim Üyesi / Görevlisi Seçiniz">
                                                            <option value="0">İlk olarak Birim Seçiniz</option>
                                                        </select>
                                                        <button class="btn btn-primary" type="button"
                                                            id="lecturerScheduleButton" data-only-table="false">
                                                            Düzenle
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Row-->
                                </div>
                                <div class="tab-pane fade" id="classroom-tab-pane" role="tabpanel"
                                    aria-labelledby="classroom-tab" tabindex="0">
                                    <!--begin::Row-->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <div class="row">
                                                <div class="col-12 col-md-4">
                                                    <select class="form-select tom-select" id="classroom_unit_id" name="classroom_unit_id">
                                                        <option value="">Birim Seçiniz</option>
                                                        <?php foreach ($units as $unit): ?>
                                                            <option value="<?= $unit->id ?>"><?= htmlspecialchars($unit->name) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <select class="form-select tom-select" id="classroom_building_id" name="classroom_building_id">
                                                        <option value="0">İlk olarak Birim Seçiniz</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <div class="input-group">
                                                        <select class="form-select" id="classroom_id" name="classroom_id">
                                                            <option value="0">İlk olarak Bina Seçiniz</option>
                                                        </select>
                                                        <button type="button" class="btn btn-primary"
                                                            id="classroomScheduleButton" data-only-table="false">
                                                            Düzenle
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Row-->
                                </div>
                            </div>
                        </div>
                        <!--end::card-body-->
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <div id="schedule_container">
                <!-- Programlar buraya yüklenecek -->
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->
<?php require __DIR__ . '/partials/schedule_notes_modal.php'; ?>