<?php
/**
 * @var string $page_title
 * @var array $departments
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
                        <li class="breadcrumb-item">Takvim İşlemleri</li>
                        <li class="breadcrumb-item active">Program Düzenle</li>
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
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Program Seçin</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                    <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"  for="department_id">Bölüm</label>
                                        <select class="form-select" id="department_id" name="department_id">
                                            <?php array_unshift($departments, (object)["id" => 0, "name" => "Bölüm Seçiniz"]);
                                            foreach ($departments as $department): ?>
                                                <option value="<?= $department->id ?>">
                                                    <?= $department->name ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"  for="program_id">Program</label>
                                        <select class="form-select" id="program_id" name="program_id">
                                            <option value=""></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!--end::Row-->

                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row Program Satırı-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">1. Yarıyıl Programı</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                    <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="available-schedule-items col-md-3 drop-zone small" data-season="1. Yarıyıl">

                                </div>
                                <div class="schedule-table col-md-9" data-season="1. Yarıyıl">

                                </div>
                            </div>
                            <!--end::Row-->
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row Program Satırı-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">3. Yarıyıl Programı</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize">
                                    <i data-lte-icon="maximize" class="bi bi-fullscreen"></i>
                                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="available-schedule-items col-md-3 drop-zone small" data-season="3. Yarıyıl">

                                </div>
                                <div class="schedule-table col-md-9" data-season="3. Yarıyıl">

                                </div>
                            </div>
                            <!--end::Row-->
                        </div>
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
