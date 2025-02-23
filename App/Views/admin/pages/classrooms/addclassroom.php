<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var string $page_title
 * @var array $classroomTypes
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
                        <li class="breadcrumb-item">Derslik İşlemleri</li>
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
                        <form action="/ajax/addClassroom" method="post" class="ajaxForm" title="Yeni Derslik Ekle">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="name"> Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı" required aria-describedby="nameHelp">
                                            <div id="nameHelp" class="form-text">
                                                Derslik için bir ad yazınız. Örn. D1
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="type"> Türü</label>
                                            <select name="type" id="type" class="form-select">
                                                <?php foreach ($classroomTypes as $id=>$type): ?>
                                                <option value="<?= $id ?>"><?= $type ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="class_size">Ders Mevcudu</label>
                                            <input type="number" class="form-control" id="class_size" name="class_size"
                                                   placeholder="Ders Mevcudu" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="">
                                            <label class="form-label" for="exam_size">Sınav Mevcudu</label>
                                            <input type="number" class="form-control" id="exam_size" name="exam_size"
                                                   placeholder="Sınav Mevcudu" required>
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