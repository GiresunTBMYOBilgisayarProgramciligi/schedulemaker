<?php
/**
 * @var \App\Models\User $currentUser Oturum açmış kullanıcı
 */
?><!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
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
                <!-- Başlangıç-->
                <li class="nav-item">
                    <a href="/admin" class="nav-link ">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>
                            Başlangıç
                        </p>
                    </a>
                </li>
                <!-- /Başlangıç-->
                <!-- Kullanıcı İşlemleri -->
                <li class="nav-item <?=(str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=
                       (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Kullanıcı İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/listusers"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listusers')) ? 'active' : ''; ?>">
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
                <!-- /Kullanıcı İşlemleri -->
                <!-- Ders İşlemleri -->
                <li class="nav-item <?=(str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=(str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book-open"></i>
                        <p>
                            Ders İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/listlessons"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listlessons')) ? 'active' : ''; ?>">
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
                    </ul>
                </li>
                <!-- /Ders İşlemleri -->
                <!-- Derslik İşlemleri -->
                <li class="nav-item <?=(str_contains($_SERVER["REQUEST_URI"], 'classroom')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=(str_contains($_SERVER["REQUEST_URI"], 'classroom')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chalkboard"></i>
                        <p>
                            Derslik İşlemleri
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/listclassrooms"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listclassrooms')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-list-alt"></i>
                                <p>
                                    Liste
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/addclassroom"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addclassroom')) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-plus"></i>
                                <p>
                                    Ekle
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- /Derslik İşlemleri -->
                <!-- Akademik Birimler -->
                <li class="nav-item <?=(str_contains($_SERVER["REQUEST_URI"], 'program') or str_contains($_SERVER["REQUEST_URI"], 'department')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'departments')) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-school"></i>
                        <p>
                            Akademik Birimler
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item <?=(str_contains($_SERVER["REQUEST_URI"], 'department') or str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Bölüm İşlemleri
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview" >
                                <li class="nav-item">
                                    <a href="/admin/listdepartments" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listdepartments')) ? 'active' : ''; ?>">
                                        <i class="nav-icon fas fa-list-alt"></i>
                                        <p>Liste</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="/admin/adddepartment" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'adddepartment')) ? 'active' : ''; ?>">
                                        <i class="nav-icon fas fa-plus"></i>
                                        <p>Ekle</p>
                                    </a>
                                </li>
                                <li class="nav-item  <?=(str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'menu-open' : ''; ?>">
                                    <a href="#" class="nav-link">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>
                                            Program İşlemleri
                                            <i class="right fas fa-angle-left"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview">
                                        <li class="nav-item">
                                            <a href="/admin/listprograms" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listprograms')) ? 'active' : ''; ?>">
                                                <i class="nav-icon fas fa-list-alt"></i>
                                                <p>Liste</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="/admin/addprogram" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addprogram')) ? 'active' : ''; ?>">
                                                <i class="nav-icon fas fa-plus"></i>
                                                <p>Ekle</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>

                    </ul>
                </li>
                <!-- /Akademik Birimler -->
                <!-- Çıkış -->
                <li class="nav-item">
                    <a href="/auth/logout" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>
                            Çıkış Yap
                        </p>
                    </a>
                </li>
                <!-- /Çıkış -->
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>