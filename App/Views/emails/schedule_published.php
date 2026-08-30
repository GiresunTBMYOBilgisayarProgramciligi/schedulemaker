<?php
use App\Core\View;
use App\Models\Schedule;
use App\Models\User;

/**
 * @var User $lecturer
 * @var Schedule $schedule
 * @var string $appUrl
 * @var string|null $unitName
 * @var string|null $departmentName
 * @var string|null $programName
 */

$academicYear = htmlspecialchars($schedule->academic_year ?? '');
$semester     = htmlspecialchars($schedule->semester ?? '');
$typeLabel    = htmlspecialchars($schedule->getScheduleTypeName());
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
    <p><strong><?= $academicYear ?> <?= $semester ?></strong> dönemi haftalık <strong><?= $typeLabel ?></strong> programınız yayınlanmıştır.</p>
    
    <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-left: 4px solid #3498db; border-radius: 4px; padding: 12px 18px; margin: 18px 0;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <?php if (!empty($unitName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d; width: 130px;"><strong>Birim:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><strong><?= htmlspecialchars($unitName) ?></strong></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($departmentName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d;"><strong>Bölüm:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><?= htmlspecialchars($departmentName) ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($programName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d;"><strong>Program:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><?= htmlspecialchars($programName) ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 4px 0; color: #6c757d;"><strong>Akademik Dönem:</strong></td>
                <td style="padding: 4px 0; color: #212529;"><?= $academicYear ?> <?= $semester ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #6c757d;"><strong>Program Türü:</strong></td>
                <td style="padding: 4px 0; color: #212529;"><?= $typeLabel ?></td>
            </tr>
        </table>
    </div>

    <p>Ders programınızın <strong>Excel (.xlsx)</strong> ve takvim uygulamalarınıza aktarabileceğiniz <strong>Takvim (.ics)</strong> dosyaları e-posta ekinde yer almaktadır.</p>
    
    <div style="margin: 25px 0;">
        <h3 style="color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 5px;">Haftalık Program Tablonuz</h3>
        <?= View::renderEmail('partials/schedule_table', ['schedule' => $schedule]) ?>
    </div>

    <div style="background-color: #f8f9fa; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; padding: 14px 18px; margin: 22px 0; border-radius: 4px; font-size: 13px; color: #334155; line-height: 1.6;">
        <strong style="color: #1e293b; display: block; margin-bottom: 6px;">💡 Yayın Yönetimi ve Bilgilendirme:</strong>
        <p style="margin: 0 0 6px 0;">
            Dilerseniz sisteme giriş yaparak <strong>Profilim &gt; Ders Programım</strong> sayfasından haftalık ders programınızın yayın durumunu dilediğiniz zaman yönetebilir ve düzenleyebilirsiniz.
        </p>
        <p style="margin: 0; font-size: 12px; color: #64748b;">
            <em>Not: Programınızda herhangi bir değişiklik veya güncelleme yapılması durumunda tarafınıza e-posta ile otomatik bilgilendirme yapılacaktır.</em>
        </p>
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
