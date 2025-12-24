<?php
/**
 * @var array $logs
 * @var \App\Core\AssetManager $assetManager
 */
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
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
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="logsTable" class="table table-striped table-hover dataTable">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
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
                                        <td><?= htmlspecialchars($log->username ?: ('#' . ($log->user_id ?? '-'))) ?></td>
                                        <td>
                                            <?= $log->getLevelHtml() ?>
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
                                            <?= $log->getSource() ?>
                                        </td>
                                        <td class="text-break" style="max-width: 240px;">
                                            <?= htmlspecialchars((string) $log->url) ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $log->ip) ?></td>
                                        <td><?= $log->getContextHtml() ?></td>
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
                Tüm log kayıtları kalıcı olarak silinecektir. Bu işlemi onaylıyor musunuz?
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
            confirmBtn.addEventListener('click', function () {
                // Modalı kapat
                const modalElement = document.getElementById('clearLogsModal');
                const modal = bootstrap.Modal.getInstance(modalElement);

                // AJAX İsteği
                $.ajax({
                    url: '/ajax/clearLogs',
                    type: 'POST',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            modal.hide();
                            // Başarı mesajı ve sayfa yenileme
                            location.reload();
                        } else {
                            alert(response.msg || 'Bir hata oluştu');
                        }
                    },
                    error: function () {
                        alert('Loglar temizlenirken sunucu hatası oluştu');
                    }
                });
            });
        }
    });
</script>