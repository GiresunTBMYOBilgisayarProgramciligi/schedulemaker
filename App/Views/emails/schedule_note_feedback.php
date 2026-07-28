<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ders Programı İstek Durumu Güncellendi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .header { background: #007bff; color: #ffffff; padding: 15px; text-align: center; border-radius: 6px 6px 0 0; }
        .content { padding: 20px 10px; line-height: 1.6; }
        .badge { display: inline-block; padding: 6px 12px; font-weight: bold; border-radius: 4px; color: #fff; }
        .badge-completed { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .badge-read { background-color: #17a2b8; }
        .feedback-box { background: #f8f9fa; border-left: 4px solid #007bff; padding: 12px; margin-top: 15px; font-style: italic; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 20px; border-top: 1px solid #eeeeee; padding-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0;">Program İstek Durumu Güncellendi</h2>
    </div>
    <div class="content">
        <p>Sayın <strong><?= htmlspecialchars($lecturer->getFullName()) ?></strong>,</p>
        <p>
            <strong><?= htmlspecialchars($note->academic_year) ?> <?= htmlspecialchars($note->semester) ?></strong> dönemi için ilettiğiniz ders/sınav programı notunuz ve isteğiniz program düzenleyici (<strong><?= htmlspecialchars($editor->getFullName()) ?></strong>) tarafından incelendi.
        </p>

        <p>
            <strong>Talebinizin Durumu:</strong>
            <?php 
                $statusEnum = $note->getStatusEnum();
                $badgeClass = match($statusEnum->value) {
                    'completed' => 'badge-completed',
                    'rejected' => 'badge-rejected',
                    default => 'badge-read'
                };
            ?>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusEnum->getLabel()) ?></span>
        </p>

        <?php if (!empty($note->editor_feedback)): ?>
            <div class="feedback-box">
                <strong>Düzenleyici Açıklaması / Geri Bildirim:</strong><br>
                <?= nl2br(htmlspecialchars($note->editor_feedback)) ?>
            </div>
        <?php endif; ?>

        <p style="margin-top: 20px;">
            Ayrıntıları kullanıcı panelinizdeki <em>Ders/Sınav Programı Notlarım</em> bölümünden de takip edebilirsiniz.
        </p>
    </div>
    <div class="footer">
        Bu e-posta otomatik olarak oluşturulmuştur. Lütfen doğrudan yanıtlamayınız.<br>
        &copy; <?= date('Y') ?> Schedule Maker
    </div>
</div>
</body>
</html>
