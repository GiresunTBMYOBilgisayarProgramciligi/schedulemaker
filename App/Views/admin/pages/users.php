<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Kullanıcı İşlemleri</h1>
                </div><!-- /.col -->

            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Main row -->
            <div class="row">
                <div class="col-12 ">
                    <div class="card card-primary card-outline card-outline-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="user-action-list-tab" data-toggle="pill"
                                       href="#custom-tabs-four-home" role="tab" aria-controls="custom-tabs-four-home"
                                       aria-selected="true">Liste</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="user-action-add-tab" data-toggle="pill"
                                       href="#user-action-add-tabContent" role="tab"
                                       aria-controls="custom-tabs-four-profile" aria-selected="false">Ekle</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="user-action-edit-tab" data-toggle="pill"
                                       href="#user-action-edit-tabContent" role="tab"
                                       aria-controls="custom-tabs-four-messages" aria-selected="false">Düzenle</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="custom-tabs-four-settings-tab" data-toggle="pill"
                                       href="#custom-tabs-four-settings" role="tab"
                                       aria-controls="custom-tabs-four-settings" aria-selected="false">Settings</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="user-action-list-tabContent">
                                <div class="tab-pane fade show active" id="custom-tabs-four-home" role="tabpanel"
                                     aria-labelledby="user-action-list-tab">
                                    <table id="user-list-table"
                                           class="table table-bordered table-hover dataTable dtr-inline">
                                        <thead>
                                        <tr>
                                            <th>İd</th>
                                            <th>e-Posta</th>
                                            <th>Adı</th>
                                            <th>Soyadı</th>
                                            <th>Kayıt Tarihi</th>
                                            <th>Son Giriş Tarihi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($user_list as $user): ?>
                                            <tr class="odd">
                                                <td><?= $user->id ?></td>
                                                <td><?= $user->mail ?></td>
                                                <td><?= $user->name ?></td>
                                                <td><?= $user->last_name ?></td>
                                                <td><?= $user->register_date ?></td>
                                                <td><?= $user->last_login ?></td>
                                            </tr>
                                        <?php endforeach; ?></tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="user-action-add-tabContent" role="tabpanel"
                                     aria-labelledby="custom-tabs-four-profile-tab">
                                    <form action="/ajax/addNewUser" method="post" class="ajaxForm">

                                        <input type="submit" value="Ekle">
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="user-action-edit-tabContent" role="tabpanel"
                                     aria-labelledby="user-action-edit-tab">
                                    Morbi turpis dolor, vulputate vitae felis non, tincidunt congue mauris. Phasellus
                                    volutpat augue id mi placerat mollis. Vivamus faucibus eu massa eget condimentum.
                                    Fusce nec hendrerit sem, ac tristique nulla. Integer vestibulum orci odio. Cras nec
                                    augue ipsum. Suspendisse ut velit condimentum, mattis urna a, malesuada nunc.
                                    Curabitur eleifend facilisis velit finibus tristique. Nam vulputate, eros non luctus
                                    efficitur, ipsum odio volutpat massa, sit amet sollicitudin est libero sed ipsum.
                                    Nulla lacinia, ex vitae gravida fermentum, lectus ipsum gravida arcu, id fermentum
                                    metus arcu vel metus. Curabitur eget sem eu risus tincidunt eleifend ac ornare
                                    magna.
                                </div>
                                <div class="tab-pane fade" id="custom-tabs-four-settings" role="tabpanel"
                                     aria-labelledby="custom-tabs-four-settings-tab">
                                    Pellentesque vestibulum commodo nibh nec blandit. Maecenas neque magna, iaculis
                                    tempus turpis ac, ornare sodales tellus. Mauris eget blandit dolor. Quisque
                                    tincidunt venenatis vulputate. Morbi euismod molestie tristique. Vestibulum
                                    consectetur dolor a vestibulum pharetra. Donec interdum placerat urna nec pharetra.
                                    Etiam eget dapibus orci, eget aliquet urna. Nunc at consequat diam. Nunc et felis ut
                                    nisl commodo dignissim. In hac habitasse platea dictumst. Praesent imperdiet
                                    accumsan ex sit amet facilisis.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <!-- /.row (main row) -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
