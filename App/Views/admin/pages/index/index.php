<?php
/**
 * @var \App\Models\User $currentUser    Oturum açmış kullanıcı
 * @var string           $dashboardRole  'admin'|'secretary'|'dept_head'|'lecturer'|'user'
 * @var array            $stats          İstatistik verileri (role göre farklı anahtarlar)
 * @var array            $recentLogs     Son sistem logları (sadece admin grubu)
 * @var array            $programs       Aktif programlar (sadece admin grubu)
 * @var array            $units          Birimler (sadece admin grubu)
 * @var \App\Models\Department $department  Bölüm detayı (sadece dept_head)
 * @var string           $scheduleHTML   Ders programı HTML (dept_head / lecturer)
 * @var array            $myLessons      Kullanıcının dersleri (sadece lecturer)
 */
use App\Core\Gate;
use App\Enums\PermissionType;
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row align-items-center">
                <div class="col">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Kullanıcı Avatarı -->
                        <img
                            src="<?= $currentUser->getGravatarURL(64) ?>"
                            alt="<?= htmlspecialchars($currentUser->getFullName()) ?>"
                            class="rounded-circle shadow-sm border border-2 border-white"
                            width="52" height="52"
                        >
                        <div>
                            <h3 class="mb-0 fw-semibold">
                                Merhaba, <?= htmlspecialchars($currentUser->getFullName()) ?> 👋
                            </h3>
                            <p class="text-muted mb-0 small">
                                <span class="badge bg-primary me-1"><?= htmlspecialchars($currentUser->getRoleName()) ?></span>
                                <?php if (!empty($currentUser->unit?->name)): ?>
                                    <span class="me-1 text-muted"><?= htmlspecialchars($currentUser->unit->name) ?></span>
                                <?php endif; ?>
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= (new DateTime())->format('d.m.Y') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->

    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">

            <?php
            // Role göre ilgili partial'ı yükle
            $partialMap = [
                'admin'     => 'dashboard_admin.php',
                'secretary' => 'dashboard_secretary.php',
                'dept_head' => 'dashboard_dept_head.php',
                'lecturer'  => 'dashboard_lecturer.php',
                'user'      => 'dashboard_user.php',
            ];
            $partialFile = $partialMap[$dashboardRole] ?? 'dashboard_user.php';
            $partialPath = __DIR__ . '/partials/' . $partialFile;

            if (file_exists($partialPath)) {
                include $partialPath;
            }
            ?>

        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->