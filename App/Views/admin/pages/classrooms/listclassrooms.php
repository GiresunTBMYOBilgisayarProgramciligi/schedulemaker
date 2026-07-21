<?php
/**
 * @var \App\Controllers\ClassroomController $classroomController
 * @var \App\Models\Classroom $classroom
 * @var string $page_title
 * @var array $classrooms
 */
use App\Core\Gate;
use App\Models\Classroom;
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Derslikler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Classroom::class)): ?>
                                <a href="/admin/addclassroom" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Derslik Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped dataTable">
                        <thead>
                        <tr>
                            <th scope="col">İd</th>
                            <th scope="col">Adı</th>
                            <th scope="col" class="filterable">Türü</th>
                            <th scope="col" class="filterable">Bina</th>
                            <th scope="col">Ders Mevcudu</th>
                            <th scope="col">Sınav Mevcudu</th>
                            <th scope="col" class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($classrooms as $classroom): ?>
                            <tr>
                                <td><?= $classroom->id ?></td>
                                <td><a href="/admin/classroom/<?= $classroom->id ?>" class="text-dark" title="Görüntüle"><?= $classroom->name ?></a></td>
                                <td><?= $classroom->getTypeName() ?></td>
                                <td><?= $classroom->building->name ?? '-' ?></td>
                                <td><?= $classroom->class_size ?></td>
                                <td><?= $classroom->exam_size ?></td>
                                <td class="text-center">
                                    <a href="/admin/editclassroom/<?= $classroom->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="/ajax/deleteclassroom/<?= $classroom->id ?>"
                                          class="ajaxFormDelete d-inline"
                                          id="deleteClassroom-<?= $classroom->id ?>"
                                          method="post">
                                        <input type="hidden" name="id" value="<?= $classroom->id ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
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
