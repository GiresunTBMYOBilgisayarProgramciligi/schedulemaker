<?php
/**
 * @var User $currentUser Oturum açmış kullanıcı
 */

use App\Core\Gate;
use App\Enums\PermissionType;
use App\Enums\UserRole;
use App\Models\User;

$requestUri = $_SERVER["REQUEST_URI"] ?? '';
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
                    <a href="/admin" class="nav-link <?= ($requestUri === '/admin' || $requestUri === '/admin/') ? 'active' : ''; ?>">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Başlangıç</p>
                    </a>
                </li>
                <!-- Profilim -->
                <li class="nav-item">
                    <a href="/admin/profile"
                        class="nav-link <?= (str_contains($requestUri, 'profile')) ? 'active' : ''; ?>">
                        <i class="nav-icon bi bi-person-badge"></i>
                        <p>Profilim</p>
                    </a>
                </li>

                <!-- Eğitim & Öğretim -->
                <?php if (Gate::allowsRole("department_head") || $currentUser->role === UserRole::PayrollOfficer->value || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_LESSONS->value) || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_SCHEDULE->value)): ?>
                <li class="nav-header">EĞİTİM & ÖĞRETİM</li>
                <?php endif; ?>
                <!-- Ders İşlemleri -->
                <?php if ($currentUser->role !== UserRole::Secretary->value && (Gate::allowsRole("department_head") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_LESSONS->value))): ?>
                    <li class="nav-item <?= (str_contains($requestUri, 'lesson')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($requestUri, 'lesson')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-journals"></i>
                            <p>
                                Ders İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listlessons" class="nav-link <?= (str_contains($requestUri, 'listlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p>Liste</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="/admin/assignlessons" class="nav-link <?= (str_contains($requestUri, 'assignlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-person-check"></i>
                                    <p>Ders Atama</p>
                                </a>
                            </li>

                            <?php if ($currentUser->role !== UserRole::PayrollOfficer->value): ?>
                            <li class="nav-item">
                                <a href="/admin/importlessons" class="nav-link <?= (str_contains($requestUri, 'importlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-in-down"></i>
                                    <p>İçe aktar</p>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- Takvim İşlemleri -->
                <?php if (Gate::allowsRole("department_head") || $currentUser->role === UserRole::PayrollOfficer->value || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_SCHEDULE->value)): ?>
                    <li class="nav-item <?= (str_contains($requestUri, 'schedule')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($requestUri, 'schedule')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-calendar"></i>
                            <p>
                                Takvim İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if ($currentUser->role !== UserRole::PayrollOfficer->value && $currentUser->role !== UserRole::Secretary->value): ?>
                            <li class="nav-item">
                                <a href="/admin/editschedule" class="nav-link <?= (str_contains($requestUri, 'editschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-calendar-plus"></i>
                                    <p>Ders Programı</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/editexamschedule" class="nav-link <?= (str_contains($requestUri, 'editexamschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-calendar2-plus"></i>
                                    <p>Sınav Programı</p>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a href="/admin/exportschedule" class="nav-link <?= (str_contains($requestUri, 'exportschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-down"></i>
                                    <p>Dışa Aktar</p>
                                </a>
                            </li>
                            <?php if ($currentUser->role !== UserRole::Secretary->value && Gate::hasAnyPermission($currentUser->id, PermissionType::PUBLISH_SCHEDULE->value)): ?>
                            <li class="nav-item">
                                <a href="/admin/publishschedule" class="nav-link <?= (str_contains($requestUri, 'publishschedule')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-globe"></i>
                                    <p>Program Yayınla</p>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Kurumsal Yapı -->
                <?php if (Gate::allowsRole("submanager") || (Gate::allowsRole("department_head", true) && (!empty($currentUser->department_id) || !empty($currentUser->program_id))) || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_UNIT->value) || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_DEPARTMENT->value) || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_PROGRAM->value)): ?>
                <li class="nav-header">KURUMSAL YAPI</li>
                <?php endif; ?>
                <?php if (Gate::allowsRole("submanager") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_UNIT->value)): ?>
                    <li class="nav-item">
                        <a href="/admin/listunits" class="nav-link <?= (str_contains($requestUri, 'unit')) ? 'active' : ''; ?>">
                            <i class="bi bi-bank nav-icon"></i>
                            <p>Üst Birim İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (Gate::allowsRole("submanager") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_DEPARTMENT->value)): ?>
                    <li class="nav-item">
                        <a href="/admin/listdepartments" class="nav-link <?= (str_contains($requestUri, 'department') && !str_contains($requestUri, '/department/')) ? 'active' : ''; ?>">
                            <i class="bi bi-buildings nav-icon"></i>
                            <p>Bölüm İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (Gate::allowsRole("submanager") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_PROGRAM->value)): ?>
                    <li class="nav-item">
                        <a href="/admin/listprograms" class="nav-link <?= (str_contains($requestUri, 'program') && !str_contains($requestUri, '/program/')) ? 'active' : ''; ?>">
                            <i class="bi bi-building nav-icon"></i>
                            <p>Program İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Bölümüm -->
                <?php if (Gate::allowsRole("department_head", true) && !empty($currentUser->department_id)): ?>
                    <li class="nav-item">
                        <a href="/admin/department/<?= (int)$currentUser->department_id ?>" class="nav-link <?= (str_contains($requestUri, 'department')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-buildings"></i>
                            <p>Bölümüm</p>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Programım -->
                <?php if (Gate::allowsRole("department_head", true) && !empty($currentUser->program_id)): ?>
                    <li class="nav-item">
                        <a href="/admin/program/<?= (int)$currentUser->program_id ?>" class="nav-link <?= (str_contains($requestUri, 'program')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-building"></i>
                            <p>Programım</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Fiziksel Altyapı -->
                <?php if (Gate::allowsRole("secretary") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_BUILDINGS->value)): ?>
                    <li class="nav-header">FİZİKSEL ALTYAPI</li>
                    <!-- Bina İşlemleri -->
                    <li class="nav-item">
                        <a href="/admin/listbuildings" class="nav-link <?= (str_contains($requestUri, 'building')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-building-fill-gear"></i>
                            <p>Bina İşlemleri</p>
                        </a>
                    </li>
                    <!-- Derslik İşlemleri -->
                    <li class="nav-item">
                        <a href="/admin/listclassrooms" class="nav-link <?= (str_contains($requestUri, 'classroom')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-door-closed-fill"></i>
                            <p>Derslik İşlemleri</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Sistem & Yönetim -->
                <?php if (Gate::allowsRole("submanager") || Gate::hasAnyPermission($currentUser->id, PermissionType::MANAGE_USERS->value)): ?>
                    <li class="nav-header">SİSTEM & YÖNETİM</li>
                    <!-- Kullanıcı İşlemleri -->
                    <li class="nav-item <?= (str_contains($requestUri, 'user')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($requestUri, 'user')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-person-fill-gear"></i>
                            <p>
                                Kullanıcı İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listusers" class="nav-link <?= (str_contains($requestUri, 'listusers')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-person-lines-fill"></i>
                                    <p>Liste</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="/admin/importusers" class="nav-link <?= (str_contains($requestUri, 'importusers')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-box-arrow-in-down"></i>
                                    <p>İçe aktar</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Ayarlar -->
                    <li class="nav-item <?= (str_contains($requestUri, 'settings') || str_contains($requestUri, 'logs')) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?= (str_contains($requestUri, 'settings') || str_contains($requestUri, 'logs')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-sliders"></i>
                            <p>
                                Ayarlar
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/settings" class="nav-link <?= (str_contains($requestUri, 'settings') && !str_contains($requestUri, 'settingslogs')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-gear"></i>
                                    <p>Ayarları Düzenle</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/editpermission" class="nav-link <?= (str_contains($requestUri, 'editpermission')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-shield-lock"></i>
                                    <p>Yetkileri Düzenle</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/logs" class="nav-link <?= (str_contains($requestUri, 'logs')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p>Kayıtlar</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/mailqueue" class="nav-link <?= (str_contains($requestUri, 'mailqueue')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-envelope-paper"></i>
                                    <p>E-posta Kuyruğu</p>
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