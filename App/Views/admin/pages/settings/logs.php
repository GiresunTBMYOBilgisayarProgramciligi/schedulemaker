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
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log->created_at) ?></td>
                                    <td><?= htmlspecialchars($log->username ?: ('#' . ($log->user_id ?? '-'))) ?></td>
                                    <td>
                                        <span class="badge bg-<?= mb_strtolower(htmlspecialchars($log->level)) == "error" ? "danger" : mb_strtolower(htmlspecialchars($log->level)) ?>"><?= htmlspecialchars($log->level) ?></span>
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
                                        <?php
                                        $src = [];
                                        if (!empty($log->file)) $src[] = basename($log->file) . ':' . $log->line;
                                        if (!empty($log->class)) $src[] = $log->class;
                                        if (!empty($log->method)) $src[] = $log->method;
                                        echo htmlspecialchars(implode(' | ', $src));
                                        ?>
                                    </td>
                                    <td class="text-break" style="max-width: 240px;">
                                        <?= htmlspecialchars((string)$log->url) ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$log->ip) ?></td>
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
