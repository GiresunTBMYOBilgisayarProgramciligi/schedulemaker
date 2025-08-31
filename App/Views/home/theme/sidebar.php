<?php
/**
 * @var \App\Models\User $currentUser Oturum açmış kullanıcı
 * @var \App\Controllers\UserController $userController
 */
use function App\Helpers\isAuthorized;
?>
<!--begin::Sidebar-->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <!--begin::Sidebar Brand-->
    <div class="sidebar-brand">
        <!--begin::Brand Link-->
        <a href="/" class="brand-link text-white">
            <!--begin::Brand Image-->
            <!--<img
                    src="../../../dist/assets/img/AdminLTELogo.png"
                    alt="AdminLTE Logo"
                    class="brand-image opacity-75 shadow"
            />-->
            <i class="opacity-75 shadow bi bi-calendar-week"></i>
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light">TMBMYO</span>
            <!--end::Brand Text-->
        </a>
        <!--end::Brand Link-->
    </div>
    <!--end::Sidebar Brand-->
    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper">
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul
                    class="nav sidebar-menu flex-column"
                    data-lte-toggle="treeview"
                    role="menu"
                    data-accordion="false"
            >
                <!-- Başlangıç-->
                <li class="nav-item">
                    <a href="/admin" class="nav-link ">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>
                            Yönetim Paneli
                        </p>
                    </a>
                </li>
                <!-- /Başlangıç-->
            </ul>
            <!--end::Sidebar Menu-->
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>
<!--end::Sidebar-->