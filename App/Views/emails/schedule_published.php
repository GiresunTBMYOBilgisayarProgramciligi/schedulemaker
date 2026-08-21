<?php
use App\Core\View;
use App\Models\Schedule;
use App\Models\User;

/**
 * @var User $lecturer
 * @var Schedule $schedule
 * @var string $appUrl
 */

$academicYear = htmlspecialchars($schedule->academic_year ?? '');
$semester     = htmlspecialchars($schedule->semester ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ders Programınız Yayınlandı</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333;">
<div style="max-width: 750px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0; line-height: 1.6;">
    <h2 style="color: #2c3e50; margin-top: 0;">Sayın <?= htmlspecialchars($lecturer->getFullName()) ?>,</h2>
    <p><strong><?= $academicYear ?> <?= $semester ?></strong> dönemi haftalık ders programınız yayınlanmıştır.</p>
    <p>Ders programınızın <strong>Excel (.xlsx)</strong> ve takvim uygulamalarınıza aktarabileceğiniz <strong>Takvim (.ics)</strong> dosyaları e-posta ekinde yer almaktadır.</p>
    
    <div style="margin: 25px 0;">
        <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">Haftalık Ders Programınız</h3>
        <?= View::renderEmail('partials/schedule_table', ['schedule' => $schedule]) ?>
    </div>

    <div style="background-color: #f8f9fa; border-left: 4px solid #17a2b8; padding: 12px 15px; margin: 20px 0; border-radius: 0 4px 4px 0; font-size: 13px; color: #495057; line-height: 1.5;">
        <strong>Önemli Not:</strong> Programda herhangi bir değişiklik yapılması durumunda tarafınıza e-posta ile bilgilendirme yapılacaktır.
    </div>

    <p style="margin-top: 25px;">
        <a href="<?= htmlspecialchars($appUrl) ?>" style="background-color: #3498db; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Sisteme Giriş Yap ve İncele</a>
    </p>

    <div style="font-size: 12px; color: #7f8c8d; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 10px;">
        Bu e-posta Schedule Maker sistemi tarafından otomatik olarak oluşturulmuştur. Lütfen doğrudan yanıtlamayınız.<br>
        &copy; <?= date('Y') ?> Schedule Maker
    </div>
</div>
</body>
</html>
