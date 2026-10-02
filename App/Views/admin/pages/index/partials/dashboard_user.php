<?php
/**
 * Dashboard Partial: Standart Kullanıcı (Fallback)
 *
 * @var User $currentUser
 */

use App\Models\User;
use function App\Helpers\e;
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body py-5">
                <img
                    src="<?= e($currentUser->getGravatarURL(80)) ?>"
                    class="rounded-circle mb-3 border border-3 border-secondary"
                    width="80" height="80"
                    alt="Profil"
                >
                <h4 class="fw-semibold"><?= e($currentUser->getFullName()) ?></h4>
                <p class="text-muted"><?= e($currentUser->getRoleName()) ?></p>
                <hr>
                <p class="text-muted small mb-3">
                    Sisteme hoş geldiniz. Profilinizi görüntülemek ve düzenlemek için aşağıdaki butona tıklayabilirsiniz.
                </p>
                <a href="/admin/profile" class="btn btn-primary">
                    <i class="bi bi-person-badge me-1"></i> Profilimi Görüntüle
                </a>
            </div>
        </div>
    </div>
</div>
