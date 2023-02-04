<?php

$l=new \App\Models\Lecturer();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TMYO Ders Programı - Kullanıcı İşlemleri</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/AdminLTE/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="/assets/AdminLTE/node_modules/bootstrap/dist/css/bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="/plugins/daterangepicker/daterangepicker.css">
    <!-- summernote -->
    <link rel="stylesheet" href="/plugins/summernote/summernote-bs4.min.css">
    <link rel="stylesheet" href="/assets/AdminLTE/node_modules/datatables.net-dt/css/jquery.dataTables.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center">
        <img class="animation__shake" src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/img/AdminLTELogo.png" alt="AdminLTELogo" height="60" width="60">
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="/admin" class="nav-link">Ana Sayfa</a>
            </li>
        </ul>

    </nav>

    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include "sidebar.php"?>

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
                                        <a class="nav-link active" id="user-action-list-tab" data-toggle="pill" href="#custom-tabs-four-home" role="tab" aria-controls="custom-tabs-four-home" aria-selected="true">Liste</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="user-action-add-tab" data-toggle="pill" href="#user-action-add-tabContent" role="tab" aria-controls="custom-tabs-four-profile" aria-selected="false">Ekle</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="user-action-edit-tab" data-toggle="pill" href="#user-action-edit-tabContent" role="tab" aria-controls="custom-tabs-four-messages" aria-selected="false">Düzenle</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="custom-tabs-four-settings-tab" data-toggle="pill" href="#custom-tabs-four-settings" role="tab" aria-controls="custom-tabs-four-settings" aria-selected="false">Settings</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="user-action-list-tabContent">
                                    <div class="tab-pane fade show active" id="custom-tabs-four-home" role="tabpanel" aria-labelledby="user-action-list-tab">
                                        <table id="user-list-table" class="table table-bordered table-hover dataTable dtr-inline">
                                            <thead>
                                                <tr>
                                                    <th>İd</th>
                                                    <th>Kullanıcı Adı</th>
                                                    <th>e-Posta</th>
                                                    <th>Adı</th>
                                                    <th>Soyadı</th>
                                                    <th>Kayıt Tarihi</th>
                                                    <th>Son Giriş Tarihi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($user_list as $user):?>
                                                <tr class="odd">
                                                    <td><?= $user->id ?></td>
                                                    <td><?= $user->user_name ?></td>
                                                    <td><?= $user->mail ?></td>
                                                    <td><?= $user->name ?></td>
                                                    <td><?= $user->last_name ?></td>
                                                    <td><?= $user->register_date ?></td>
                                                    <td><?= $user->last_login ?></td>
                                            </tr>
                                            <?php endforeach; ?></tbody>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="user-action-add-tabContent" role="tabpanel" aria-labelledby="custom-tabs-four-profile-tab">
                                        <form action="/ajax/addNewUser" method="post" class="ajaxForm" >

                                            <input type="submit" value="Ekle">
                                        </form>
                                    </div>
                                    <div class="tab-pane fade" id="user-action-edit-tabContent" role="tabpanel" aria-labelledby="user-action-edit-tab">
                                        Morbi turpis dolor, vulputate vitae felis non, tincidunt congue mauris. Phasellus volutpat augue id mi placerat mollis. Vivamus faucibus eu massa eget condimentum. Fusce nec hendrerit sem, ac tristique nulla. Integer vestibulum orci odio. Cras nec augue ipsum. Suspendisse ut velit condimentum, mattis urna a, malesuada nunc. Curabitur eleifend facilisis velit finibus tristique. Nam vulputate, eros non luctus efficitur, ipsum odio volutpat massa, sit amet sollicitudin est libero sed ipsum. Nulla lacinia, ex vitae gravida fermentum, lectus ipsum gravida arcu, id fermentum metus arcu vel metus. Curabitur eget sem eu risus tincidunt eleifend ac ornare magna.
                                    </div>
                                    <div class="tab-pane fade" id="custom-tabs-four-settings" role="tabpanel" aria-labelledby="custom-tabs-four-settings-tab">
                                        Pellentesque vestibulum commodo nibh nec blandit. Maecenas neque magna, iaculis tempus turpis ac, ornare sodales tellus. Mauris eget blandit dolor. Quisque tincidunt venenatis vulputate. Morbi euismod molestie tristique. Vestibulum consectetur dolor a vestibulum pharetra. Donec interdum placerat urna nec pharetra. Etiam eget dapibus orci, eget aliquet urna. Nunc at consequat diam. Nunc et felis ut nisl commodo dignissim. In hac habitasse platea dictumst. Praesent imperdiet accumsan ex sit amet facilisis.
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
    <?php include "footer.php"?>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="/assets/AdminLTE/node_modules/jquery/dist/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<script src="/assets/AdminLTE/node_modules/jquery-ui-dist/jquery-ui.min.js"></script>
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
    $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
<script src="/assets/AdminLTE/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="/assets/js/gettext.php"></script>
<script src="/assets/js/ajax.js"></script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/pages/dashboard.min.js"></script>
<script src="/assets/AdminLTE/node_modules/datatables.net/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function () {
        $('.dataTable').DataTable();
    });
</script>
</body>
</html>
