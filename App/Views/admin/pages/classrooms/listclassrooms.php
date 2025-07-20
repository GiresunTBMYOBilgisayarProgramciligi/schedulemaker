<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Models\Classroom $classroom
 * @var string $page_title
 * @var array $classrooms
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
                        <li class="breadcrumb-item active">Liste</li>
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
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                        <tr>
                            <th scope="col">İd</th>
                            <th scope="col">Adı</th>
                            <th scope="col" class="filterable">Türü</th>
                            <th scope="col">Ders Mevcudu</th>
                            <th scope="col">Sınav Mevcudu</th>
                            <th scope="col" class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($classrooms as $classroom): ?>
                            <tr>
                                <td><?= $classroom->id ?></td>
                                <td><?= $classroom->name ?></td>
                                <td><?= $classroom->getTypeName() ?></td>
                                <td><?= $classroom->class_size ?></td>
                                <td><?= $classroom->exam_size ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-primary dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            İşlemler
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/classroom/<?= $classroom->id ?>">Gör</a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/editclassroom/<?= $classroom->id ?>">Düzenle</a>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="/ajax/deleteclassroom/<?= $classroom->id ?>"
                                                      class="ajaxFormDelete"
                                                      id="deleteClassroom-<?= $classroom->id ?>"
                                                      method="post">
                                                    <input type="hidden" name="id"
                                                           value="<?= $classroom->id ?>">
                                                    <input type="submit" class="dropdown-item" value="Sil">
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->
