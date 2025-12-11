<?php
/**
 * @var int $department_id
 * @var array $departments
 * @var \App\Models\Department $department
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
                        <li class="breadcrumb-item">Program İşlemleri</li>
                        <li class="breadcrumb-item active">Ekle</li>
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
                    <div class="card">
                        <form action="/ajax/addProgram" method="post" class="ajaxForm" title="Yeni Program Ekle">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="department_id">Bölüm</label>
                                            <select class="form-select tom-select" id="department_id" name="department_id">
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?= $department->id ?>"
                                                        <?= $department->id == $department_id ? "selected" : "" ?>><?= $department->name ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-check form-switch pt-4 mt-3">
                                            <input name="active" class="form-check-input" type="checkbox" id="flexSwitchCheckChecked"
                                                   checked>
                                            <label class="form-check-label" for="flexSwitchCheckChecked">Aktif</label>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Ekle</button>
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