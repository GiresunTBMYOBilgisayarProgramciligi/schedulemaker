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
        <a href="/admin" class="brand-link text-white">
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
                            Başlangıç
                        </p>
                    </a>
                </li>
                <!-- /Başlangıç-->
                <!-- Kullanıcı İşlemleri -->
                <?php if (isAuthorized("submanager")): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'menu-open' : ''; ?>">
                        <a href="#"
                           class="nav-link <?=
                           (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-person-fill-gear"></i>
                            <p>
                                Kullanıcı İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listusers"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listusers')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-person-lines-fill"></i>
                                    <p>
                                        Liste
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/adduser"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'adduser')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-person-plus-fill"></i>
                                    <p>
                                        Ekle
                                    </p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- /Kullanıcı İşlemleri -->
                <!-- Ders İşlemleri -->
                <?php if (isAuthorized("submanager")): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'menu-open' : ''; ?>">
                        <a href="#"
                           class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'lesson')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-journals"></i>
                            <p>
                                Ders İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listlessons"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listlessons')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <p>
                                        Liste
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/addlesson"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addlesson')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-journal-plus"></i>
                                    <p>
                                        Ekle
                                    </p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- /Ders İşlemleri -->
                <!-- Derslik İşlemleri -->
                <?php if (isAuthorized("submanager")): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'classroom')) ? 'menu-open' : ''; ?>">
                        <a href="#"
                           class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'classroom')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-door-closed-fill"></i>
                            <p>
                                Derslik İşlemleri
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/admin/listclassrooms"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listclassrooms')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-list-ul"></i>
                                    <p>
                                        Liste
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/admin/addclassroom"
                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addclassroom')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-plus"></i>
                                    <p>
                                        Ekle
                                    </p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- /Derslik İşlemleri -->
                <!-- Akademik Birimler -->
                <?php if (isAuthorized("lecturer")): ?>
                    <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'program') or str_contains($_SERVER["REQUEST_URI"], 'department')) ? 'menu-open' : ''; ?>">
                        <a href="#"
                           class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'departments')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-mortarboard"></i>
                            <p>
                                Akademik Birimler
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if (isAuthorized("department_head",true)): ?>
                            <li class="nav-item">
                                <a href="/admin/department/<?=$currentUser->department_id?>" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'department')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-buildings"></i>
                                    <p>
                                        Bölümüm
                                    </p>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (isAuthorized("department_head",true)): ?>
                            <li class="nav-item">
                                <a href="/admin/program/<?=$currentUser->program_id?>" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'active' : ''; ?>">
                                    <i class="nav-icon bi bi-building"></i>
                                    <p>
                                        Programım
                                    </p>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (isAuthorized("submanager")): ?>
                            <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'department') or str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'menu-open' : ''; ?>">
                                <a href="#" class="nav-link">
                                    <i class="bi bi-buildings nav-icon"></i>
                                    <p>
                                        Bölüm İşlemleri
                                        <i class="nav-arrow bi bi-chevron-right"></i>
                                    </p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item">
                                        <a href="/admin/listdepartments"
                                           class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listdepartments')) ? 'active' : ''; ?>">
                                            <i class="nav-icon bi bi-list-ul"></i>
                                            <p>Liste</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="/admin/adddepartment"
                                           class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'adddepartment')) ? 'active' : ''; ?>">
                                            <i class="nav-icon bi bi-plus"></i>
                                            <p>Ekle</p>
                                        </a>
                                    </li>
                                    <li class="nav-item  <?= (str_contains($_SERVER["REQUEST_URI"], 'program')) ? 'menu-open' : ''; ?>">
                                        <a href="#" class="nav-link">
                                            <i class="bi bi-building nav-icon"></i>
                                            <p>
                                                Program İşlemleri
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            <li class="nav-item">
                                                <a href="/admin/listprograms"
                                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listprograms')) ? 'active' : ''; ?>">
                                                    <i class="nav-icon bi bi-list-ul"></i>
                                                    <p>Liste</p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="/admin/addprogram"
                                                   class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'addprogram')) ? 'active' : ''; ?>">
                                                    <i class="nav-icon bi bi-building-add"></i>
                                                    <p>Ekle</p>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- /Akademik Birimler -->
                <!-- Takvim  -->
                <?php if (isAuthorized("department_head")): ?>
                <li class="nav-item <?= (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'menu-open' : ''; ?>">
                    <a href="#"
                       class="nav-link <?=
                       (str_contains($_SERVER["REQUEST_URI"], 'user')) ? 'active' : ''; ?>">
                        <i class="nav-icon bi bi-calendar"></i>
                        <p>
                            Takvim İşlemleri
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/editschedule"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'listusers')) ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-lines-fill"></i>
                                <p>
                                    Ders Programını Düzenle
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#"
                               class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'adduser')) ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-plus-fill"></i>
                                <p>
                                    Sınav Programını Düzenle
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <!-- /Takvim -->
                <!-- Ayarlar -->
                <?php if (isAuthorized("submanager")): ?>
                    <li class="nav-item">
                        <a href="/admin/settings" class="nav-link <?= (str_contains($_SERVER["REQUEST_URI"], 'settings')) ? 'active' : ''; ?>">
                            <i class="nav-icon bi bi-sliders"></i>
                            <p>
                                Ayarlar
                            </p>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- /Ayarlar -->
            </ul>
            <!--end::Sidebar Menu-->
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>
<!--end::Sidebar-->