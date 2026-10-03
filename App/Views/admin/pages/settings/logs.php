<?php
/**
 * @var array $logs
 * @var AssetManager $assetManager
 * @var array $channels
 * @var string $selectedChannel
 * @var string $selectedLevel
 */

use App\Core\AssetManager;
use App\Helpers\LogViewHelper;
use function App\Helpers\e;

$selectedChannel = $selectedChannel ?? 'all';
$selectedLevel = $selectedLevel ?? '';
$channels = $channels ?? [];
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">Kayıtlar</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                        data-bs-target="#clearLogsModal">
                        <i class="bi bi-trash"></i> Logları Temizle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtreleme Alanı -->
            <div class="card card-outline card-secondary mb-3">
                <div class="card-body py-2">
                    <form method="GET" action="/admin/logs" class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="channelSelect" class="col-form-label fw-bold small"><i class="bi bi-diagram-3 me-1"></i>Kanal:</label>
                        </div>
                        <div class="col-auto">
                            <select name="channel" id="channelSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?= $selectedChannel === 'all' ? 'selected' : '' ?>>Tüm Kanallar</option>
                                <?php foreach ($channels as $ch): ?>
                                    <option value="<?= htmlspecialchars($ch) ?>" <?= $selectedChannel === $ch ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($ch)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto ms-md-2">
                            <label for="levelSelect" class="col-form-label fw-bold small"><i class="bi bi-funnel me-1"></i>Seviye:</label>
                        </div>
                        <div class="col-auto">
                            <select name="level" id="levelSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="" <?= empty($selectedLevel) ? 'selected' : '' ?>>Tüm Seviyeler</option>
                                <?php foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'] as $lvl): ?>
                                    <option value="<?= $lvl ?>" <?= $selectedLevel === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selectedChannel !== 'all' || !empty($selectedLevel)): ?>
                            <div class="col-auto ms-auto">
                                <a href="/admin/logs" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Filtreleri Sıfırla
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="logsTable" class="table table-striped table-hover dataTable"
                            data-order='[[0, "desc"]]'>
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th class="filterable">Kanal</th>
                                    <th class="filterable">Kullanıcı</th>
                                    <th class="filterable">Seviye</th>
                                    <th>Mesaj</th>
                                    <th>Kaynak</th>
                                    <th>URL</th>
                                    <th class="filterable">IP</th>
                                    <th>Context</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log->created_at) ?></td>
                                        <td><?= LogViewHelper::renderChannelBadge($log->channel ?? 'app') ?></td>
                                        <td><?= htmlspecialchars($log->username ?: ('#' . ($log->user_id ?? '-'))) ?></td>
                                        <td>
                                            <?= LogViewHelper::renderLevelBadge($log) ?>
                                        </td>
                                        <td class="text-wrap" style="max-width: 420px; white-space: normal;">
                                            <?= htmlspecialchars($log->message) ?>
                                            <?php if (!empty($log->trace)): ?>
                                                <details>
                                                    <summary>Detay</summary>
                                                    <pre class="mb-0"
                                                        style="white-space: pre-wrap;"><?= htmlspecialchars($log->trace) ?></pre>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= LogViewHelper::renderSource($log) ?>
                                        </td>
                                        <td class="text-break" style="max-width: 240px;">
                                            <?= htmlspecialchars((string) $log->url) ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $log->ip) ?></td>
                                        <td><?= LogViewHelper::renderContextModal($log) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Logları Temizle Onay Modalı -->
<div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearLogsModalLabel">Logları Temizle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p>Log dosyaları kalıcı olarak silinecektir. Bu işlemi onaylıyor musunuz?</p>
                <?php if ($selectedChannel !== 'all'): ?>
                    <div class="alert alert-warning py-2 mb-0">
                        <strong>Kanal Filtresi Aktif:</strong> Sadece <code><?= htmlspecialchars($selectedChannel) ?></code> kanalına ait dosyalar temizlenecektir.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger py-2 mb-0">
                        <strong>Tüm Kanallar:</strong> Sisteme ait tüm log dosyaları temizlenecektir.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger" id="confirmClearLogs">Evet, Temizle</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const confirmBtn = document.getElementById('confirmClearLogs');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', async function () {
                const modalElement = document.getElementById('clearLogsModal');
                const modal = bootstrap.Modal.getInstance(modalElement);
                const activeChannel = '<?= htmlspecialchars($selectedChannel) ?>';

                try {
                    const response = await fetch('/ajax/clearLogs', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            channel: activeChannel !== 'all' ? activeChannel : null
                        })
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        if (modal) {
                            modal.hide();
                        }
                        location.reload();
                    } else {
                        alert(data.msg || 'Bir hata oluştu');
                    }
                } catch (error) {
                    alert('Loglar temizlenirken sunucu hatası oluştu');
                }
            });
        }
    });
</script>