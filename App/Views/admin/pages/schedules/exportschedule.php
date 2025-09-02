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
                        <li class="breadcrumb-item active">Program Dışa aktar</li>
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
                        <div class="card-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label" for="department_id">Bölüm</label>
                                        <select class="form-select tom-select" id="department_id" name="department_id">
                                            <?php array_unshift($departments, (object)["id" => 0, "name" => "Bölüm Seçiniz"]);
                                            foreach ($departments as $department): ?>
                                                <option value="<?= $department->id ?>">
                                                    <?= $department->name ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="nameHelp" class="form-text">
                                            Bölüm seçilmezse tüm programlar dışa aktarılır
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label" for="program_id">Program</label>
                                        <select class="form-select" id="program_id" name="program_id">
                                            <option value="0">İlk olarak Bölüm seçiniz</option>
                                        </select>
                                        <div id="nameHelp" class="form-text">
                                            Program seçilmezse tüm programlar dışa aktarılır
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label" for="academic_year]">Dönem</label>
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
                                </div>
                            </div>
                            <!--end::Row-->
                        </div>
                        <!--end::card-body-->
                        <div class="card-footer card-primary">
                            <div class="row">
                                <div class="text-end">
                                    <button class="btn btn-primary" type="button"  id="departmentAndProgramExport">
                                           Dışa aktar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--start::Row-->
            <div class="row">
                <div class="col-6">
                    <div class="card card-primary card-outline">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label" for="lecturer_id">Hoca</label>
                                        <select class="form-select tom-select" id="lecturer_id" name="lecturer_id" >
                                            <option></option>
                                            <?php foreach ($lecturers as $lecturer): ?>
                                                <option value="<?= $lecturer->id ?>"><?= $lecturer->getFullName() ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="nameHelp" class="form-text">
                                            Hoca seçilmezse tüm hoca programları dışa aktarılır
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::card-body-->
                        <div class="card-footer card-primary">
                            <div class="row">
                                <div class="text-end">
                                    <button class="btn btn-primary" type="button" id="lecturerExport" >
                                        Dışa aktar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card card-primary card-outline">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label" for="classroom_id">Derslik</label>
                                        <select class="form-select" id="classroom_id" name="classroom_id">
                                            <option value="0">Derslik Seçiniz</option>
                                            <?php foreach ($classrooms as $classroom): ?>
                                                <option value="<?= $classroom->id ?>"><?= $classroom->name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="nameHelp" class="form-text">
                                            Derslik seçilmezse tüm derslik programları dışa aktarılır
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::card-body-->
                        <div class="card-footer card-primary">
                            <div class="row">
                                <div class="text-end">
                                    <button class="btn btn-primary" type="button" id="classroomExport">
                                        Dışa aktar
                                    </button>
                                </div>
                            </div>
                        </div>
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
