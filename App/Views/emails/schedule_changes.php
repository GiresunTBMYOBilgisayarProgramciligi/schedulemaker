<?php
use App\Models\User;

/**
 * @var User $lecturer
 * @var array $changes
 * @var string $appUrl
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ders/Sınav Programınızda Değişiklik Yapıldı</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333;">
<div style="max-width: 650px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0; line-height: 1.6;">
    <h2 style="color: #2c3e50; margin-top: 0;">Sayın <?= htmlspecialchars($lecturer->getFullName()) ?>,</h2>
    <p>Ders/sınav programınızda aşağıdaki değişiklikler yapılmıştır:</p>
    
    <div style="background: #f8f9fa; border-left: 4px solid #f39c12; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0;">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($changes as $change): ?>
                <li style="margin-bottom: 8px;">
                    <strong><?= htmlspecialchars($change->detail) ?></strong>
                    <span style="color: #7f8c8d; font-size: 12px;">(<?= htmlspecialchars($change->created_at) ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <p>Lütfen sistem üzerinden güncel programınızı kontrol ediniz.</p>

    <p style="margin-top: 25px;">
        <a href="<?= htmlspecialchars($appUrl) ?>" style="background-color: #3498db; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Sisteme Giriş Yap</a>
    </p>

    <div style="font-size: 12px; color: #7f8c8d; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 10px;">
        Bu e-posta Schedule Maker sistemi tarafından otomatik olarak oluşturulmuştur. Lütfen doğrudan yanıtlamayınız.<br>
        &copy; <?= date('Y') ?> Schedule Maker
    </div>
</div>
</body>
</html>
