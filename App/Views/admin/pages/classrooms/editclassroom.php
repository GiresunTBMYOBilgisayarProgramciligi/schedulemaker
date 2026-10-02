<?php
/**
 * @var \App\Models\Classroom $classroom
 * @var string $page_title
 * @var array $classroomTypes
 * @var \App\Models\Building[] $buildings
 * @var bool|null $has_multiple_building_units
 */
use function App\Helpers\renderBuildingSelectOptions;
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= e($page_title) ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Derslik İşlemleri</li>
                        <li class="breadcrumb-item active">Düzenle</li>
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
                        <form action="/ajax/updateClassroom" method="post" class="ajaxForm updateForm"
                              title="Derslik Bilgilerini Güncelle">
                            <input type="hidden" name="id" value="<?= $classroom->id ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı" value="<?= e($classroom->name) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="building_id">Bina</label>
                                            <select name="building_id" id="building_id" class="form-select tom-select" required>
                                                <?= renderBuildingSelectOptions($buildings ?? [], (int)($classroom->building_id ?? 0) ?: null, $has_multiple_building_units ?? null, 'Bina seçiniz...') ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="">
                                            <label class="form-label" for="type"> Türü</label>
                                            <select name="type" id="type" class="form-select">
                                                <?php foreach ($classroomTypes as $id => $type): ?>
                                                    <option value="<?= (int)$id ?>" <?= $classroom->type == $id ? "selected" : "" ?>><?= e($type) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="">
                                            <label class="form-label" for="class_size">Ders Mevcudu</label>
                                            <input type="number" class="form-control" id="class_size" name="class_size"
                                                   placeholder="Ders Mevcudu" value="<?= (int)$classroom->class_size ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="">
                                            <label class="form-label" for="exam_size">Sınav Mevcudu</label>
                                            <input type="number" class="form-control" id="exam_size" name="exam_size"
                                                   placeholder="Sınav Mevcudu" value="<?= (int)$classroom->exam_size ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Güncelle</button>
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




