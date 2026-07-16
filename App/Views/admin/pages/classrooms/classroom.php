<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Models\Classroom $classroom
 * @var string $page_title
 * @var string $scheduleHTML
 * @var string $midtermScheduleHTML
 * @var string $finalScheduleHTML
 * @var string $makeupScheduleHTML
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
                        <li class="breadcrumb-item active">Derslik</li>
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
                    <!-- Ders Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Derslik Bilgileri</h5>
                            <div class="card-tools">

                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Derslik Adı</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($classroom->name, ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt class="col-sm-4">Bina</dt>
                                <dd class="col-sm-8"><?= $classroom->building?->name ?? 'Belirtilmemiş' ?></dd>
                                <dt class="col-sm-4">Ders Mevcudu</dt>
                                <dd class="col-sm-8"><?= $classroom->class_size ?></dd>
                                <dt class="col-sm-4">Sınav Mevcudu</dt>
                                <dd class="col-sm-8"><?= $classroom->exam_size ?></dd>
                                <dt class="col-sm-4">Türü</dt>
                                <dd class="col-sm-8"><?= $classroom->getTypeName() ?></dd>
                            </dl>
                        </div>
                        <div class="card-footer">
                            <a href="/admin/editclassroom/<?= $classroom->id ?>" class="btn btn-primary">Dersliği Düzenle</a>
                            <form action="/ajax/deleteclassroom/<?= $classroom->id ?>" class="ajaxFormDelete d-inline" method="post">
                                <input type="hidden" name="id" value="<?= $classroom->id ?>">
                                <input type="submit" class="btn btn-danger" value="Sil">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline card-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="classroomTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="schedule-tab" data-bs-toggle="pill" href="#schedule"
                                        role="tab" aria-controls="schedule" aria-selected="true">Ders Programı</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="exams-tab" data-bs-toggle="pill" href="#exams" role="tab"
                                        aria-controls="exams" aria-selected="false">Sınav Programı</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="classroomTabsContent">
                                <div class="tab-pane fade show active" id="schedule" role="tabpanel"
                                    aria-labelledby="schedule-tab">
                                    <?= $scheduleHTML ?>
                                </div>
                                <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                                    <!-- Nested Tabs for Exams -->
                                    <ul class="nav nav-tabs mb-3" id="examTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="midterm-tab" data-bs-toggle="tab"
                                                href="#midterm" role="tab">Ara Sınav</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="final-tab" data-bs-toggle="tab" href="#final"
                                                role="tab">Final</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="makeup-tab" data-bs-toggle="tab" href="#makeup"
                                                role="tab">Bütünleme</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="examTabsContent">
                                        <div class="tab-pane fade show active" id="midterm" role="tabpanel">
                                            <?= $midtermScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="final" role="tabpanel">
                                            <?= $finalScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="makeup" role="tabpanel">
                                            <?= $makeupScheduleHTML ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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