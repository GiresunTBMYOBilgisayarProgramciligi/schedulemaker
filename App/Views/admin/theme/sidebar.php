<!-- Main Sidebar Container -->
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
                <img src="<?=$currentUser->getGravatarURL()?>"
                     class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?= $currentUser->getFullName()?></a>
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
                <li class="nav-item">
                    <a href="pages/calendar.html" class="nav-link <?= (strpos($_SERVER["REQUEST_URI"], 'calendar') !== false) ? 'active' : ''; ?>">
                        <i class="nav-icon far fa-calendar-alt"></i>
                        <p>
                            Calendar
                            <span class="badge badge-info right">2</span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/users" class="nav-link <?= (strpos($_SERVER["REQUEST_URI"], 'users') !== false) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Kullanıcı İşlemleri
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/lessons" class="nav-link <?= (strpos($_SERVER["REQUEST_URI"], 'lessons') !== false) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book-open"></i>
                        <p>
                            Ders İşlemleri
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/classrooms" class="nav-link <?= (strpos($_SERVER["REQUEST_URI"], 'classrooms') !== false) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chalkboard"></i>
                        <p>
                            Derslik İşlemleri
                        </p>
                    </a>
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