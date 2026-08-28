<?php
/**
 * @var string $page_title
 * @var array  $stats
 * @var array  $items
 * @var string $cronCommand
 * @var string $scriptPath
 * @var string $logPath
 * @var string $phpBin
 * @var \App\Models\User $currentUser
 */
use App\Enums\MailQueueStatus;
?>

<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0"><i class="bi bi-envelope-paper me-2"></i><?= $page_title ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item"><a href="/admin/settings">Ayarlar</a></li>
                        <li class="breadcrumb-item active" aria-current="page">E-posta Kuyruğu</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content Header-->

    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">

            <!-- 1. İstatistik Kartları (Info Boxes) -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-hourglass-split"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Bekleyen E-posta</span>
                            <span class="info-box-number fs-4" id="stat-pending"><?= number_format($stats['pending'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-arrow-repeat"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">İşlenen</span>
                            <span class="info-box-number fs-4" id="stat-processing"><?= number_format($stats['processing'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Başarıyla Gönderilen</span>
                            <span class="info-box-number fs-4" id="stat-sent"><?= number_format($stats['sent'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-danger shadow-sm"><i class="bi bi-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Başarısız / Hatalı</span>
                            <span class="info-box-number fs-4" id="stat-failed"><?= number_format($stats['failed'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Hızlı İşlemler & Crontab Rehber Kartı -->
            <div class="row g-3 mb-4">
                <!-- Manuel Kuyruk Yönetimi -->
                <div class="col-lg-5">
                    <div class="card card-outline card-primary h-100 shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-play-circle me-1"></i> Manuel Kuyruk Yönetimi</h3>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <p class="text-muted small mb-3">
                                Crontab çalışmıyorsa veya anlık toplu gönderim başlatmak istiyorsanız aşağıdaki butonları kullanabilirsiniz.
                            </p>
                            <div class="d-grid gap-2">
                                <button type="button" id="btn-process-queue" class="btn btn-primary btn-lg" data-batch-size="<?= $batchSize ?? 10 ?>">
                                    <i class="bi bi-send-fill me-1"></i> Kuyruğu Şimdi Çalıştır (<?= $batchSize ?? 10 ?> Gönder)
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" id="btn-retry-failed" class="btn btn-outline-warning flex-fill" <?= ($stats['failed'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                        <i class="bi bi-arrow-clockwise me-1"></i> Hatalıları Yeniden Dene
                                    </button>
                                    <button type="button" id="btn-clear-sent" class="btn btn-outline-secondary flex-fill" <?= ($stats['sent'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash me-1"></i> Gönderilenleri Temizle
                                    </button>
                                </div>
                                <a href="/admin/settings#mail" class="btn btn-outline-dark btn-sm mt-1">
                                    <i class="bi bi-gear me-1"></i> SMTP & Mail Ayarlarını Düzenle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crontab Kurulum ve Otomasyon Rehberi -->
                <div class="col-lg-7">
                    <div class="card card-outline card-success h-100 shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-clock-history me-1"></i> Sunucu Crontab Kurulum Rehberi</h3>
                            <div class="card-tools">
                                <span class="badge bg-success-subtle text-success border border-success">Otomatik Gönderim</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="small mb-2 text-muted">
                                E-postaların arka planda sunucuyu yormadan her dakika otomatik gönderilmesi için sunucunuzun crontab listesine aşağıdaki satırı ekleyin:
                            </p>

                            <!-- Kopyalanabilir Komut -->
                            <div class="input-group mb-3">
                                <span class="input-group-text bg-dark text-white"><i class="bi bi-terminal"></i></span>
                                <input type="text" class="form-control font-monospace bg-light" id="cronCommandInput" value="<?= htmlspecialchars($cronCommand) ?>" readonly>
                                <button class="btn btn-success" type="button" id="btn-copy-cron" title="Panoya Kopyala">
                                    <i class="bi bi-clipboard me-1"></i> Kopyala
                                </button>
                            </div>

                            <!-- Kurulum Adımları -->
                            <div class="row g-2 text-muted small mt-2">
                                <div class="col-sm-4">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <strong class="text-dark d-block mb-1">1. Terminali Açın</strong>
                                        <code>crontab -e</code> komutunu çalıştırın.
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <strong class="text-dark d-block mb-1">2. Satırı Yapıştırın</strong>
                                        Yukarıdaki satırı dosyanın sonuna ekleyin.
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <strong class="text-dark d-block mb-1">3. Kaydedin</strong>
                                        Dosyayı kaydedip çıkın. Otomasyon hazırdır!
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. E-posta Kuyruğu Tablosu -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-list-ul me-1"></i> Kuyruk Kayıtları (Son 200)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-table">
                            <i class="bi bi-arrow-repeat me-1"></i> Yenile
                        </button>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" id="mailQueueTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 100px;">Durum</th>
                                <th>Alıcı</th>
                                <th>Konu</th>
                                <th style="width: 90px;" class="text-center">Ekler</th>
                                <th style="width: 80px;" class="text-center">Deneme</th>
                                <th style="width: 150px;">Oluşturulma</th>
                                <th style="width: 150px;">Gönderilme</th>
                                <th style="width: 80px;" class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        Kuyrukta henüz kayıt bulunmamaktadır.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $statusBadge = match ($item->status) {
                                        MailQueueStatus::Pending->value    => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Bekliyor</span>',
                                        MailQueueStatus::Processing->value => '<span class="badge bg-info"><i class="bi bi-arrow-repeat me-1"></i>İşleniyor</span>',
                                        MailQueueStatus::Sent->value       => '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Gönderildi</span>',
                                        MailQueueStatus::Failed->value     => '<span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>Başarısız</span>',
                                        default                            => '<span class="badge bg-secondary">' . htmlspecialchars($item->status ?? '') . '</span>',
                                    };

                                    $hasAttachments = !empty($item->attachments);
                                    $attList = $hasAttachments ? json_decode($item->attachments, true) : [];
                                    $attCount = is_array($attList) ? count($attList) : 0;
                                    ?>
                                    <tr id="queue-row-<?= $item->id ?>">
                                        <td class="text-muted small"><?= $item->id ?></td>
                                        <td><?= $statusBadge ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item->to_name ?: 'İsimsiz') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($item->to_email ?? '') ?></div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($item->subject ?? '') ?>">
                                                <?= htmlspecialchars($item->subject ?? '') ?>
                                            </div>
                                            <?php if (!empty($item->error_message)): ?>
                                                <div class="small text-danger text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($item->error_message) ?>">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($item->error_message) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($attCount > 0): ?>
                                                <span class="badge bg-light text-dark border" title="<?= $attCount ?> adet ek dosya">
                                                    <i class="bi bi-paperclip me-1"></i><?= $attCount ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= (int)$item->attempts > 0 ? 'bg-secondary' : 'bg-light text-dark border' ?>">
                                                <?= (int)$item->attempts ?>/3
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $item->created_at ? (new DateTime($item->created_at))->format('d.m.Y H:i:s') : '-' ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $item->sent_at ? (new DateTime($item->sent_at))->format('d.m.Y H:i:s') : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-view-detail"
                                                    data-id="<?= $item->id ?>"
                                                    data-email="<?= htmlspecialchars($item->to_email ?? '') ?>"
                                                    data-name="<?= htmlspecialchars($item->to_name ?? '') ?>"
                                                    data-subject="<?= htmlspecialchars($item->subject ?? '') ?>"
                                                    data-status="<?= htmlspecialchars($item->status ?? '') ?>"
                                                    data-error="<?= htmlspecialchars($item->error_message ?? '') ?>"
                                                    title="İçeriği İncele">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-delete-item" data-id="<?= $item->id ?>" title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->

<!-- E-posta Detay Modalı -->
<div class="modal fade" id="mailDetailModal" tabindex="-1" aria-labelledby="mailDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mailDetailModalLabel"><i class="bi bi-envelope-open me-2"></i>E-posta Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-sm-3 text-muted">Alıcı:</div>
                        <div class="col-sm-9 fw-semibold" id="modalTo"></div>
                        <div class="col-sm-3 text-muted">Konu:</div>
                        <div class="col-sm-9 fw-semibold" id="modalSubject"></div>
                        <div class="col-sm-3 text-muted">Durum:</div>
                        <div class="col-sm-9" id="modalStatus"></div>
                    </div>
                </div>

                <div id="modalErrorContainer" class="alert alert-danger d-none mb-3">
                    <strong class="d-block mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Hata Detayı:</strong>
                    <span id="modalErrorMessage" class="font-monospace small"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Güvenli ve fallback destekli panoya kopyalama fonksiyonu
    function copyToClipboard(text, onSuccess) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(function() {
                fallbackCopy(text, onSuccess);
            });
        } else {
            fallbackCopy(text, onSuccess);
        }
    }

    function fallbackCopy(text, onSuccess) {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if (successful && onSuccess) {
                onSuccess();
            }
        } catch (err) {
            console.error('Kopyalama başarısız oldu:', err);
        }
    }

    // Crontab Kopyala
    const btnCopyCron = document.getElementById('btn-copy-cron');
    const cronInput = document.getElementById('cronCommandInput');
    if (btnCopyCron && cronInput) {
        btnCopyCron.addEventListener('click', function() {
            cronInput.select();
            copyToClipboard(cronInput.value, function() {
                const originalHtml = btnCopyCron.innerHTML;
                btnCopyCron.innerHTML = '<i class="bi bi-check-lg me-1"></i> Kopyalandı!';
                btnCopyCron.classList.replace('btn-success', 'btn-dark');
                setTimeout(() => {
                    btnCopyCron.innerHTML = originalHtml;
                    btnCopyCron.classList.replace('btn-dark', 'btn-success');
                }, 2000);
            });
        });
    }

    // Kuyruğu Şimdi Çalıştır (Process Queue)
    const btnProcessQueue = document.getElementById('btn-process-queue');
    if (btnProcessQueue) {
        btnProcessQueue.addEventListener('click', function() {
            const batchSize = btnProcessQueue.getAttribute('data-batch-size') || 10;
            btnProcessQueue.disabled = true;
            btnProcessQueue.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> E-postalar Gönderiliyor...';

            fetch('/ajax/processMailQueue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({ limit: batchSize })
            })
            .then(res => res.json())
            .then(data => {
                btnProcessQueue.disabled = false;
                btnProcessQueue.innerHTML = `<i class="bi bi-send-fill me-1"></i> Kuyruğu Şimdi Çalıştır (${batchSize} Gönder)`;
                if (data.status === 'success') {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Kuyruk İşlendi',
                            text: data.msg,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        alert(data.msg);
                        location.reload();
                    }
                } else {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Hata', text: data.msg || 'Bir hata oluştu.' });
                    } else {
                        alert(data.msg || 'Hata oluştu');
                    }
                }
            })
            .catch(err => {
                btnProcessQueue.disabled = false;
                btnProcessQueue.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kuyruğu Şimdi Çalıştır (10 Gönder)';
                alert('Bağlantı hatası: ' + err.message);
            });
        });
    }

    // Hatalıları Yeniden Dene
    const btnRetryFailed = document.getElementById('btn-retry-failed');
    if (btnRetryFailed) {
        btnRetryFailed.addEventListener('click', function() {
            if (!confirm('Başarısız olmuş tüm e-postalar tekrar bekleyen kuyruğuna alınsın mı?')) return;

            fetch('/ajax/retryFailedMailQueue', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.msg || 'Hata oluştu');
                }
            })
            .catch(err => {
                alert('Hata: ' + err.message);
            });
        });
    }

    // Gönderilenleri Temizle
    const btnClearSent = document.getElementById('btn-clear-sent');
    if (btnClearSent) {
        btnClearSent.addEventListener('click', function() {
            if (!confirm('Başarıyla gönderilmiş tüm kuyruk kayıtları silinsin mi?')) return;

            fetch('/ajax/clearSentMailQueue', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.msg || 'Hata oluştu');
                }
            })
            .catch(err => {
                alert('Hata: ' + err.message);
            });
        });
    }

    // Tekil Kayıt Sil
    document.querySelectorAll('.btn-delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (!confirm(`Bu e-posta kuyruk kaydını (#${id}) silmek istediğinize emin misiniz?`)) return;

            fetch('/ajax/deleteMailQueueItem', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById(`queue-row-${id}`);
                    if (row) row.remove();
                } else {
                    alert(data.msg || 'Kayıt silinemedi');
                }
            })
            .catch(err => {
                alert('Hata: ' + err.message);
            });
        });
    });

    // Detay Modalı Açma
    document.querySelectorAll('.btn-view-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const subject = this.getAttribute('data-subject');
            const status = this.getAttribute('data-status');
            const error = this.getAttribute('data-error');

            document.getElementById('modalTo').textContent = (name ? name + ' ' : '') + '<' + email + '>';
            document.getElementById('modalSubject').textContent = subject;
            document.getElementById('modalStatus').innerHTML = `<span class="badge bg-secondary">${status}</span>`;

            const errContainer = document.getElementById('modalErrorContainer');
            const errMessage = document.getElementById('modalErrorMessage');
            if (error && error.trim() !== '') {
                errMessage.textContent = error;
                errContainer.classList.remove('d-none');
            } else {
                errContainer.classList.add('d-none');
            }

            const modal = new bootstrap.Modal(document.getElementById('mailDetailModal'));
            modal.show();
        });
    });

    // Yenile
    const btnRefresh = document.getElementById('btn-refresh-table');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', function() {
            location.reload();
        });
    }
});
</script>
