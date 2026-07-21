<?php
/**
 * @var \App\Controllers\LessonController $lessonController
 * @var \App\Models\Lesson $lesson
 * @var array $lessons
 * @var string $page_title
 */
use App\Core\Gate;
use App\Models\Lesson;
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Dersler</h3>
                            <div class="card-tools">
                                <?php if (Gate::check("create", Lesson::class)): ?>
                                <a href="/admin/addlesson" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Yeni Ders Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                        <tr>
                            <th scope="col">İd</th>
                            <th scope="col">Kodu</th>
                            <th scope="col" class="filterable">Adı</th>
                            <th scope="col" class="filterable">Türü</th>
                            <th scope="col">Mevcudu</th>
                            <th scope="col">Saati</th>
                            <th scope="col" class="filterable">Yarıyılı</th>
                            <th scope="col" class="filterable">Hocası</th>
                            <th scope="col" class="filterable">Bölüm</th>
                            <th scope="col" class="filterable">Program</th>
                            <th scope="col" class="filterable">Dönem</th>
                            <th scope="col" class="filterable">Yıl</th>
                            <th scope="col" class="filterable">Bina</th>
                            <th scope="col">Derslik türü</th>

                            <th scope="col" class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?= $lesson->id ?></td>
                                <td><?= $lesson->code . ($lesson->group_no > 0 ? '.' . $lesson->group_no : '') ?></td>
                                <td
                                    <?= $lesson->parentLesson ? 'data-bs-toggle="popover" data-bs-trigger="hover" title="Bağlı Ders" data-bs-content="'.$lesson->parentLesson->getFullName(addCode: true, addProgram: true).' Dersine bağlı"' : '' ?>
                                >
                                    <a href="/admin/lesson/<?= $lesson->id ?>" class="text-dark" title="Görüntüle">
                                        <?= $lesson->parentLesson ? $lesson->name . "*" : $lesson->name ?>
                                    </a>
                                </td>
                                <td><?= $lesson->getTypeName() ?></td>
                                <td><?= $lesson->size ?></td>
                                <td><?= $lesson->hours ?></td>
                                <td><?= $lesson->semester_no ?></td>
                                <td><?= $lesson->lecturer->getFullName() ?></td>
                                <td><?= $lesson->department?->name ?? '<span class="text-danger">—</span>' ?></td>
                                <td><?= $lesson->program?->name ?? '<span class="text-danger">—</span>' ?></td>
                                <td><?= $lesson->semester ?></td>
                                <td><?= $lesson->academic_year ?></td>
                                <td><?= $lesson->building?->name ?? '<span class="text-danger">—</span>' ?></td>
                                <td><?= $lesson->getClassroomTypeName() ?></td>

                                <td class="text-center">
                                    <?php if (Gate::check("update", $lesson)): ?>
                                    <a href="/admin/editlesson/<?= $lesson->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (Gate::check("delete", $lesson)): ?>
                                    <form action="/ajax/deletelesson/<?= $lesson->id ?>"
                                          class="ajaxFormDelete d-inline"
                                          id="deleteLesson-<?= $lesson->id ?>"
                                          method="post">
                                        <input type="hidden" name="id" value="<?= $lesson->id ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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