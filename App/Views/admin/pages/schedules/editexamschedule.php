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
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Takvim İşlemleri</li>
                        <li class="breadcrumb-item active">Sınav Programını Düzenle</li>
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
                        <!-- .card-header -->
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title flex-fill">Bölüm ve Program Sınav Programı</h3>
                            <div class="flex-fill">
                                <div class="input-group">
                                    <select class="form-select" id="academic_year" name="academic_year">
                                        <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                            <option value="<?= $year . ' - ' . $year + 1 ?>" <?= getSettingValue("academic_year") == $year . ' - ' . $year + 1 ? 'selected' : '' ?>>
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
                                </div>
                            </div>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="row">
                                        <div class="col-12 col-md-6">
                                            <select class="form-select tom-select" id="department_id" name="department_id">
                                                <?php array_unshift($departments, (object)["id" => 0, "name" => "Bölüm Seçiniz"]);
                                                foreach ($departments as $department): ?>
                                                    <option value="<?= $department->id ?>">
                                                        <?= $department->name ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="input-group">
                                                <select class="form-select" id="program_id" name="program_id">
                                                    <option value="0">İlk olarak Bölüm seçiniz</option>
                                                </select>
                                                <button type="button" class="btn btn-primary"
                                                        id="departmentAndProgramScheduleButton"
                                                        data-only-table="false"
                                                        data-schedule-type="exam">
                                                    Göster
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!--end::Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class=" input-group mb-3">
                                        <select class="form-select tom-select " id="lecturer_id" name="lecturer_id"
                                                placeholder=" Öğretim Üyesi / Görevlisi Seçimek izin yazınız">
                                            <option></option>
                                            <?php foreach ($lecturers as $lecturer): ?>
                                                <option value="<?= $lecturer->id ?>"><?= $lecturer->getFullName() ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-primary" type="button" id="lecturerScheduleButton" data-only-table="false" data-schedule-type="exam">
                                            Göster
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group mb-3">
                                        <select class="form-select" id="classroom_id" name="classroom_id">
                                            <option value="0">Derslik Seçiniz</option>
                                            <?php foreach ($classrooms as $classroom): ?>
                                                <option value="<?= $classroom->id ?>"><?= $classroom->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-primary" type="button" id="classroomScheduleButton" data-only-table="false" data-schedule-type="exam">
                                            Göster
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden templates for modal selects -->
                            <div class="d-none">
                                <select id="classroom_options_template">
                                    <option value="">Derslik Seçiniz</option>
                                    <?php foreach ($classrooms as $classroom): ?>
                                        <option value="<?= $classroom->id ?>" data-exam-size="<?= htmlspecialchars($classroom->exam_size ?? '', ENT_QUOTES) ?>">
                                            <?= $classroom->name ?> (<?= htmlspecialchars($classroom->exam_size ?? '', ENT_QUOTES) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="lecturer_options_template">
                                    <option value="">Gözetmen Seçiniz</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?= $lecturer->id ?>"><?= $lecturer->getFullName() ?></option>
                                    <?php endforeach; ?>
                                </select>
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
