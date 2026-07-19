<?php
/**
 * @var \App\Models\User $currentUser Oturum açmış kullanıcı
 */
use App\Core\Gate;
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
                <!-- Ana Menü -->
                <li class="nav-header">ANA MENÜ</li>
                <!-- Başlangıç-->
                <li class="nav-item">
                    <a href="/admin" class="nav-link <?= ($_SERVER["REQUEST_URI"] === '/admin' || $_SERVER["REQUEST_URI"] === '/admin/') ? 'active' : ''; ?>">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Başlangıç</p>
                    </a>
                </li>
                <!-- Profilim -->
                <li class="nav-item">
                    <a href="/admin/profile"
                        class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'profile')) ? 'active' : ''; ?>">
                        <i class="nav-icon bi bi-person-badge"></i>
                        <p>Profilim</p>
                    </a>
                </li>

                <!-- Eğitim & Öğretim -->
                <?php if (Gate::allowsRole("department_head") || Gate::hasAnyPermission($currentUser->id, \App\Enums\PermissionType::MANAGE_LESSONS->value) || Gate::hasAnyPermission($currentUser->id, \App\Enums\PermissionType::MANAGE_SCHEDULE->value)): ?>
                <li class="nav-header">EĞİTİM & ÖĞRETİM</li>
                <?php endif; ?>
                <!-- Ders İşlemleri -->
                <?php if (Gate::allowsRole("submanager") || Gate::hasAnyPermission($currentUser->id, \App\Enums\PermissionType::MANAGE_LESSONS->value)): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-journals"></i>
                            <p>
                                Ders İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listlessons" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p>Liste</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="/admin/importlessons" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'importlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-in-down"></i>
                                    <p>İçe aktar</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- Takvim İşlemleri -->
                <?php if (Gate::allowsRole("department_head") || Gate::hasAnyPermission($currentUser->id, \App\Enums\PermissionType::MANAGE_SCHEDULE->value)): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'schedule')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'schedule')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-calendar"></i>
                            <p>
                                Takvim İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/editschedule" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'editschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-calendar-plus"></i>
                                    <p>Ders Programı</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/editexamschedule" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'editexamschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-calendar2-plus"></i>
                                    <p>Sınav Programı</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/exportschedule" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'exportschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-down"></i>
                                    <p>Dışa Aktar</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Kurumsal Yapı -->
                <?php if (Gate::allowsRole("submanager") || (Gate::allowsRole("department_head", true) && (!empty($currentUser->department_id) || !empty($currentUser->program_id)))): ?>
                <li class="nav-header">KURUMSAL YAPI</li>
                <?php endif; ?>
                <?php if (Gate::allowsRole("submanager")): ?>
                    <li class="nav-item">
                        <a href="/admin/listunits" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'unit')) ? 'active' : ''; ?>">
                            <i class="bi bi-bank nav-icon"></i>
                            <p>Üst Birim İşlemleri</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/listdepartments" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'department')) ? 'active' : ''; ?>">
                            <i class="bi bi-buildings nav-icon"></i>
                            <p>Bölüm İşlemleri</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/listprograms" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'active' : ''; ?>">
                            <i class="bi bi-building nav-icon"></i>
                            <p>Program İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Bölümüm -->
                <?php if (Gate::allowsRole("department_head", true) && !empty($currentUser->department_id)): ?>
                    <li class="nav-item">
                        <a href="/admin/department/<?= $currentUser->department_id ?>" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'department')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-buildings"></i>
                            <p>Bölümüm</p>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Programım -->
                <?php if (Gate::allowsRole("department_head", true) && !empty($currentUser->program_id)): ?>
                    <li class="nav-item">
                        <a href="/admin/program/<?= $currentUser->program_id ?>" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-building"></i>
                            <p>Programım</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Fiziksel Altyapı -->
                <?php if (Gate::allowsRole("submanager")): ?>
                    <li class="nav-header">FİZİKSEL ALTYAPI</li>
                    <!-- Bina İşlemleri -->
                    <li class="nav-item">
                        <a href="/admin/listbuildings" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'building')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-building-fill-gear"></i>
                            <p>Bina İşlemleri</p>
                        </a>
                    </li>
                    <!-- Derslik İşlemleri -->
                    <li class="nav-item">
                        <a href="/admin/listclassrooms" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'classroom')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-door-closed-fill"></i>
                            <p>Derslik İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Sistem & Yönetim -->
                <?php if (Gate::allowsRole("submanager")): ?>
                    <li class="nav-header">SİSTEM & YÖNETİM</li>
                    <!-- Kullanıcı İşlemleri -->
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-person-fill-gear"></i>
                            <p>
                                Kullanıcı İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listusers" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listusers')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-person-lines-fill"></i>
                                    <p>Liste</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="/admin/importusers" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'importusers')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-in-down"></i>
                                    <p>İçe aktar</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Ayarlar -->
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'settings') || str_contains($_SERVER["REQUEST_URI"], 'logs')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'settings') || str_contains($_SERVER["REQUEST_URI"], 'logs')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-sliders"></i>
                            <p>
                                Ayarlar
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/settings" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'settings') && !str_contains($_SERVER["REQUEST_URI"], 'settingslogs')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-gear"></i>
                                    <p>Ayarları Düzenle</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/editpermission" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'editpermission')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-shield-lock"></i>
                                    <p>Yetkileri Düzenle</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/logs" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'logs')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p>Kayıtlar</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <!--end::Sidebar Menu-->
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>
<!--end::Sidebar-->