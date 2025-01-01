<?php
/**
 * @var \App\Models\User $currentUser Oturum açmış kullanıcı
 */
?><!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="/admin" class="brand-link">
        <img src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/img/AdminLTELogo.png" alt="AdminLTE Logo"
             class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">TMYO Ders Programı</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?= $currentUser->getGravatarURL() ?>"
                     class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="/admin/profile" class="d-block"><?= $currentUser->getFullName() ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Add icons to the links using the .nav-icon class
                     with font-awesome or any other icon font library -->
                <li class="nav-item">
                    <a href="/admin" class="nav-link ">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>
                            Başlangıç
                        </p>
                    </a>
                </li>
                <li class="nav-item <?=
                (str_contains($_SERVER["REQUEST_URI"], 'users') or
                    str_contains($_SERVER["REQUEST_URI"], 'adduser')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=
                       (str_contains($_SERVER["REQUEST_URI"], 'users') or
                           str_contains($_SERVER["REQUEST_URI"], 'adduser')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Kullanıcı İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/users"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'users')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-address-book"></i>
                                <p>
                                    Liste
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/adduser"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'adduser')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-user-plus"></i>
                                <p>
                                    Ekle
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item <?=
                (str_contains($_SERVER["REQUEST_URI"], 'lessons') or
                    str_contains($_SERVER["REQUEST_URI"], 'addlesson') or
                    str_contains($_SERVER["REQUEST_URI"], 'editlesson')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=
                       (str_contains($_SERVER["REQUEST_URI"], 'lessons') or
                           str_contains($_SERVER["REQUEST_URI"], 'addlesson') or
                           str_contains($_SERVER["REQUEST_URI"], 'editlesson')
                       ) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book-open"></i>
                        <p>
                            Ders İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/lessons"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'lessons')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-list-alt"></i>
                                <p>
                                    Liste
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/addlesson"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addlesson')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-book-medical"></i>
                                <p>
                                    Ekle
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/editlesson"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'editlesson')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-edit"></i>
                                <p>
                                    Düzenle
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item <?=
                (str_contains($_SERVER["REQUEST_URI"], 'classrooms') or
                    str_contains($_SERVER["REQUEST_URI"], 'addclassrooms') or
                    str_contains($_SERVER["REQUEST_URI"], 'editclassroom')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=
                       (str_contains($_SERVER["REQUEST_URI"], 'classrooms') or
                           str_contains($_SERVER["REQUEST_URI"], 'addclassroom') or
                           str_contains($_SERVER["REQUEST_URI"], 'editclassroom')
                       ) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chalkboard"></i>
                        <p>
                            Derslik İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/lessons"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'classrooms')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-list-alt"></i>
                                <p>
                                    Liste
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/addlesson"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addclassroom')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-plus"></i>
                                <p>
                                    Ekle
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/editlesson"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'editclassroom')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-edit"></i>
                                <p>
                                    Düzenle
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="/admin/departments"
                       class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'departments')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-school"></i>
                        <p>
                            Akademik Birimler
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Bölüm İşlemleri
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview" style="display: none;">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Liste</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Ekle</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Düzenle</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Program İşlemleri
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview" style="display: none;">
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Liste</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Ekle</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>Düzenle</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="/auth/logout" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>
                            Çıkış Yap
                        </p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>