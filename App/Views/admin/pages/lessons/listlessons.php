<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 * @var array $lessons
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
                        <li class="breadcrumb-item">Ders İşlemleri</li>
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
                            <th scope="col">Kodu</th>
                            <th scope="col">Adı</th>
                            <th scope="col">Türü</th>
                            <th scope="col">Mevcudu</th>
                            <th scope="col">Saati</th>
                            <th scope="col">Dönemi</th>
                            <th scope="col">Hocası</th>
                            <th scope="col">Bölüm</th>
                            <th scope="col">Program</th>
                            <th scope="col">Dönem</th>
                            <th scope="col">Yıl</th>
                            <th scope="col">Derslik türü</th>

                            <th scope="col" class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?= $lesson->id ?></td>
                                <td><?= $lesson->code ?></td>
                                <td><?= $lesson->name ?></td>
                                <td><?= $lesson->type ?></td>
                                <td><?= $lesson->size ?></td>
                                <td><?= $lesson->hours ?></td>
                                <td><?= $lesson->semester_no ?></td>
                                <td><?= $lesson->getLecturer()->getFullName() ?></td>
                                <td><?= $lesson->getDepartment()->name ?></td>
                                <td><?= $lesson->getProgram()->name ?></td>
                                <td><?= $lesson->semester ?></td>
                                <td><?= $lesson->academic_year ?></td>
                                <td><?= $lesson->getClassroomTypeName() ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-primary dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            İşlemler
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/lesson/<?=$lesson->id?>">Gör</a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/editlesson/<?=$lesson->id?>">Düzenle</a>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="/ajax/deletelesson/<?=$lesson->id?>"
                                                      class="ajaxFormDelete"
                                                      id="deleteLesson-<?=$lesson->id?>"
                                                      method="post">
                                                    <input type="hidden" name="id"
                                                           value="<?=$lesson->id?>">
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