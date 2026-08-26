<?php
/**
 * @var string $subject
 * @var string $recipientStr
 * @var string $dateStr
 * @var string $attachmentBadges
 * @var string $body
 */
?>
<div class="card mb-4 shadow-sm border-primary mail-card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-envelope-fill me-2"></i><strong><?= $subject ?></strong>
        </div>
        <span class="badge bg-light text-dark"><?= $dateStr ?></span>
    </div>
    <div class="card-body">
        <div class="mb-2">
            <strong>Alıcı:</strong> <span class="badge bg-info text-dark"><?= $recipientStr ?></span>
        </div>
        <div class="mb-3">
            <strong>Ekler:</strong> <?= $attachmentBadges ?>
        </div>
        <hr>
        <div class="email-content p-3 bg-white rounded border">
            <?= $body ?>
        </div>
    </div>
</div>
