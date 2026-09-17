<?php
/**
 * @var string $page_title
 * @var array $programs
 * @var int|null $selected_program_id
 * @var array $lecturers
 * @var array $buildings
 * @var array $classroomTypes
 * @var array $lessonTypes
 * @var string $current_academic_year
 * @var string $current_semester
 */
use App\Core\Gate;
use App\Models\Lesson;
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= htmlspecialchars($page_title) ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Ders İşlemleri</li>
                        <li class="breadcrumb-item active">Ders Atama</li>
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
            <!-- Filtre Kartı -->
            <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-funnel me-1"></i> Dönem ve Program Seçimi</h3>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3 align-items-end">
                        <!-- Akademik Yıl -->
                        <div class="col-xl-2 col-md-3">
                            <label for="academic_year" class="form-label fw-bold">Akademik Yıl</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <?php for ($year = 2023; $year <= (int)date('Y') + 1; $year++): ?>
                                    <?php $yOption = $year . ' - ' . ($year + 1); ?>
                                    <option value="<?= $yOption ?>" <?= $current_academic_year === $yOption ? 'selected' : '' ?>>
                                        <?= $yOption ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Dönem -->
                        <div class="col-xl-2 col-md-3">
                            <label for="semester" class="form-label fw-bold">Dönem</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="Güz" <?= $current_semester === 'Güz' ? 'selected' : '' ?>>Güz</option>
                                <option value="Bahar" <?= $current_semester === 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                                <option value="Yaz" <?= $current_semester === 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                            </select>
                        </div>

                        <!-- Program -->
                        <div class="col-xl-3 col-md-6">
                            <label for="program_id" class="form-label fw-bold">Program</label>
                            <select class="form-select" id="program_id" name="program_id">
                                <?php if (empty($programs)): ?>
                                    <option value="">Tanımlı program bulunamadı</option>
                                <?php else: ?>
                                    <?php foreach ($programs as $prog): ?>
                                        <option value="<?= $prog->id ?>" 
                                                data-department-id="<?= (int)($prog->department_id ?? 0) ?>"
                                                <?= (int)$selected_program_id === (int)$prog->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prog->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Aksiyon Butonları -->
                        <div class="col-xl-5 col-md-12 text-md-end d-flex flex-wrap gap-1 justify-content-md-end align-items-center">
                            <button type="button" id="btnFetchLessons" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Dersleri Listele
                            </button>
                            <button type="button" id="btnExportExcel" class="btn btn-success" title="Seçili programın ders atama listesini Excel formatında indir">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel İndir
                            </button>
                            <?php if (Gate::allowsRole(\App\Enums\UserRole::DepartmentHead)): ?>
                                <button type="button" id="btnExportAllExcel" class="btn btn-outline-success" title="Yetkili olduğunuz tüm programların ders atama listesini tek dosyada indir">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Tüm Programları Excel İndir
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dersler Kartı -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title m-0"><i class="bi bi-list-check me-1"></i> Ders Listesi ve Hoca Atamaları</h3>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <?php if (Gate::check("create", Lesson::class)): ?>
                            <a href="/admin/addlesson/<?= $selected_program_id ?? '' ?>" id="btnAddLesson" class="btn btn-sm btn-outline-primary" title="Seçili programa yeni ders ekle">
                                <i class="bi bi-plus-lg me-1"></i> Ders Ekle
                            </a>
                        <?php endif; ?>
                        <button type="button" id="btnToggleAllLecturers" class="btn btn-sm btn-outline-secondary" data-show-all="false" title="Tüm üniversite hocalarını hoca listelerine ekle/kaldır">
                            <i class="bi bi-globe me-1"></i> Tüm Hocaları Göster
                        </button>
                        <button type="button" class="btn btn-sm btn-success btn-save-all d-none">
                            <i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" id="assignmentTable">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">Kodu</th>
                                <th style="width: 60px;" class="text-center">Grup</th>
                                <th>Dersin Adı</th>
                                <th style="width: 110px;" class="text-center">Yarıyıl</th>
                                <th style="width: 140px;">Türü</th>
                                <th style="width: 100px;" class="text-center">Mevcut</th>
                                <th style="width: 90px;" class="text-center">Saati</th>
                                <th style="width: 250px;">Öğretim Elemanı</th>
                                <th style="width: 160px;">Derslik Türü</th>
                                <th style="width: 150px;">Bina</th>
                                <th style="width: 80px;" class="text-center">İşlem</th>
                            </tr>
                            </thead>
                            <tbody id="assignmentTableBody">
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>
                                    Dersleri görüntülemek için yukarıdan seçim yapıp "Dersleri Listele" butonuna tıklayınız.
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Card Footer (Alt kaydet butonu) -->
                <div class="card-footer text-end py-2">
                    <button type="button" class="btn btn-success btn-save-all d-none">
                        <i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet
                    </button>
                </div>
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->

<!-- Seçenek Şablonları (JS tarafından kullanılır) -->
<template id="lecturerOptionsTemplate">
    <option value="">-- Atanmamış --</option>
    <?php foreach ($lecturers as $lec): ?>
        <option value="<?= $lec->id ?>" 
                data-department-id="<?= (int)($lec->department_id ?? 0) ?>"
                data-department-name="<?= htmlspecialchars($lec->department?->name ?? 'Diğer') ?>">
            <?= htmlspecialchars($lec->getFullName(true)) ?>
        </option>
    <?php endforeach; ?>
</template>

<template id="lessonTypeOptionsTemplate">
    <?php foreach ($lessonTypes as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
    <?php endforeach; ?>
</template>

<template id="classroomTypeOptionsTemplate">
    <option value="">-- Seçiniz --</option>
    <?php foreach ($classroomTypes as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
    <?php endforeach; ?>
</template>

<template id="semesterNoOptionsTemplate">
    <?php foreach ($semesterNoList as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
    <?php endforeach; ?>
</template>

<template id="buildingOptionsTemplate">
    <option value="">-- Seçiniz --</option>
    <?php foreach ($buildings as $bld): ?>
        <option value="<?= $bld->id ?>"><?= htmlspecialchars($bld->name) ?></option>
    <?php endforeach; ?>
</template>
