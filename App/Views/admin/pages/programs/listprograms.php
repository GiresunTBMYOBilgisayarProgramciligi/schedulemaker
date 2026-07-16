<?php
/**
 * @var \App\Controllers\ProgramController $programController
 * @var \App\Models\Program $program
 * @var string $page_title
 * @var array $programs
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
                            <th scope="col" class="filterable">Bölüm</th>
                            <th scope="col">Aktif</th>
                            <th scope="col" class="text-center">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <td><?= $program->id ?></td>
                                <td><?= $program->name ?></td>
                                <td><?= $program->department?->name ?></td>
                                <td>
                                    <div class="form-check form-switch ">
                                        <input name="active" class="form-check-input" type="checkbox"
                                               id="flexSwitchCheckChecked"
                                                <?= $program->active ? "checked" : "" ?> disabled>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="/admin/program/<?= $program->id ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="/admin/editprogram/<?= $program->id ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="/ajax/deleteprogram/<?= $program->id ?>"
                                          class="ajaxFormDelete d-inline"
                                          id="deleteProgram-<?= $program->id ?>"
                                          method="post"
                                          data-confirm-message="Programı sildiğinizde bu programa ait tüm dersler de silinecektir. Devam etmek istiyor musunuz?">
                                        <input type="hidden" name="id" value="<?= $program->id ?>">
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
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->