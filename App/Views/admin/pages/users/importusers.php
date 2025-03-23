<?php
/**
 * @var \App\Controllers\UserController $userController
 * @var \App\Models\User $user kullanıcı listesinde döngüde kullanılan user değişkeni
 * @var array $departments \App\Models\Department->getDepartments())
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
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">İçe aktar</li>
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
                    <div class="card ">
                        <form id="importUserForm" action="/ajax/importUsers" method="post" class="ajaxForm" enctype="multipart/form-data"
                              title="Kullanıcıları içe aktar">
                            <div class="card-body pb-0">
                                <div class="row">
                                    <div class="mb-3">
                                        <label for="importFile" class="form-label"></label>
                                        <input class="form-control" type="file" id="importFile" name="importFile" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="/admin/download/Hoca listesi.xlsx" type="button" class="btn btn-success">Şablon İndir</a>
                                <button type="submit" class="btn btn-primary">İçe aktar</button>
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
