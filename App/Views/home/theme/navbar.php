<?php
/**
 * @var \App\Models\User $currentUser Oturum açmış kullanıcı
 */
?>
<!--begin::Header-->
<nav class="app-header navbar navbar-expand bg-body">
    <!--begin::Container-->
    <div class="container-fluid">
        <!--begin::Start Navbar Links-->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
        </ul>
        <!--end::Start Navbar Links-->
        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                </a>
            </li>
            <!--end::Fullscreen Toggle-->
            <!--begin::User Menu Dropdown-->
            <?php if ($currentUser):?>

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img
                            src="<?= $currentUser->getGravatarURL(50) ?>"
                            class="user-image rounded-circle shadow"
                            alt="User Image"
                    />
                    <span class="d-none d-md-inline"><?= $currentUser->getFullName() ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!--begin::User Image-->
                    <li class="user-header text-bg-primary">
                        <img
                                src="<?= $currentUser->getGravatarURL(90) ?>"
                                class="rounded-circle shadow"
                                alt="User Image"
                        />
                        <p>
                            <?= $currentUser->getFullName() ?>
                            <small><?= $currentUser->getLastLogin() ?></small>
                        </p>
                    </li>
                    <!--end::User Image-->
                    <!--begin::Menu Footer-->
                    <li class="user-footer">
                        <a href="/admin/profile" class="btn btn-light btn-flat">Profil</a>
                        <a href="/auth/logout" class="btn btn-light btn-flat float-end">Çıkış Yap</a>
                    </li>
                    <!--end::Menu Footer-->
                </ul>
            </li>
            <?php endif; ?>
            <!--end::User Menu Dropdown-->
        </ul>
        <!--end::End Navbar Links-->
    </div>
    <!--end::Container-->
</nav>
<!--end::Header-->