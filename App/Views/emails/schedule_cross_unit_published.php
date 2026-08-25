<?php
/**
 * @var \App\Models\User $lecturer
 * @var string $unitName
 * @var string $scheduleType
 * @var string $semester
 * @var string $academicYear
 * @var string $appUrl
 */

$academicYear = htmlspecialchars($academicYear ?? '');
$semester     = htmlspecialchars($semester ?? '');
$unitName     = htmlspecialchars($unitName ?? '');
$scheduleType = htmlspecialchars($scheduleType ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Girdiğiniz Derslerin Programı Yayınlandı</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333;">
<div style="max-width: 750px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e0e0e0; line-height: 1.6;">
    <h2 style="color: #2c3e50; margin-top: 0;">Sayın <?= htmlspecialchars($lecturer->getFullName()) ?>,</h2>
    <p><strong><?= $unitName ?></strong> bünyesinde girdiğiniz derslerin <strong><?= $academicYear ?> <?= $semester ?></strong> dönemi <strong><?= $scheduleType ?></strong> yayınlanmıştır.</p>
    
    <div style="background-color: #f8f9fa; border-left: 4px solid #17a2b8; padding: 12px 15px; margin: 20px 0; border-radius: 0 4px 4px 0; font-size: 13px; color: #495057; line-height: 1.5;">
        <strong>Bilgilendirme:</strong> Kendi asıl kullanıcı ders programınız, kadronuzun bulunduğu birim yetkililerince yayınlandığında ayrıca e-posta ile tarafınıza iletilecektir. Bu e-posta yalnızca belirtilen birimdeki girdiğiniz dersler hakkında bilgilendirme amaçlıdır.
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
