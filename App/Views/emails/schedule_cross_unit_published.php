<?php
/**
 * @var \App\Models\User $lecturer
 * @var string $unitName
 * @var string $scheduleType
 * @var string $semester
 * @var string $academicYear
 * @var string $appUrl
 * @var string|null $departmentName
 * @var string|null $programName
 * @var array $lessonNames
 * @var string|null $ownAffiliationName
 */

$academicYear       = htmlspecialchars($academicYear ?? '');
$semester           = htmlspecialchars($semester ?? '');
$unitName           = htmlspecialchars($unitName ?? '');
$scheduleType       = htmlspecialchars($scheduleType ?? '');
$departmentName     = htmlspecialchars($departmentName ?? '');
$programName        = htmlspecialchars($programName ?? '');
$ownAffiliationName = htmlspecialchars($ownAffiliationName ?? '');
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
    <p>
        <?php if (!empty($programName)): ?>
            <strong><?= $programName ?></strong> programında
        <?php elseif (!empty($departmentName)): ?>
            <strong><?= $departmentName ?></strong> bölümünde
        <?php else: ?>
            <strong><?= $unitName ?></strong> bünyesinde
        <?php endif; ?>
        girdiğiniz derslere ait <strong><?= $academicYear ?> <?= $semester ?></strong> dönemi <strong><?= $scheduleType ?></strong> yayınlanmıştır.
    </p>
    
    <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-left: 4px solid #3498db; border-radius: 4px; padding: 12px 18px; margin: 18px 0;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <?php if (!empty($unitName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d; width: 160px;"><strong>Yayınlanan Birim:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><strong><?= $unitName ?></strong></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($departmentName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d;"><strong>Yayınlanan Bölüm:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><?= $departmentName ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($programName)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d;"><strong>Yayınlanan Program:</strong></td>
                    <td style="padding: 4px 0; color: #212529;"><?= $programName ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 4px 0; color: #6c757d;"><strong>Akademik Dönem:</strong></td>
                <td style="padding: 4px 0; color: #212529;"><?= $academicYear ?> <?= $semester ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #6c757d;"><strong>Program Türü:</strong></td>
                <td style="padding: 4px 0; color: #212529;"><?= $scheduleType ?></td>
            </tr>
            <?php if (!empty($lessonNames)): ?>
                <tr>
                    <td style="padding: 4px 0; color: #6c757d; vertical-align: top;"><strong>Bu Kapsamdaki Dersleriniz:</strong></td>
                    <td style="padding: 4px 0; color: #212529;">
                        <ul style="margin: 0; padding-left: 18px;">
                            <?php foreach ($lessonNames as $lessonName): ?>
                                <li><strong><?= htmlspecialchars($lessonName) ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; margin: 20px 0; border-radius: 0 4px 4px 0; font-size: 13px; color: #856404; line-height: 1.5;">
        <strong>Önemli Bilgilendirme:</strong> Kadronuzun bulunduğu 
        <?php if (!empty($ownAffiliationName)): ?>
            <strong><?= $ownAffiliationName ?></strong>
        <?php else: ?>
            kendi biriminiz/programınız
        <?php endif; ?> 
        ders programı yayınlandığında, tüm derslerinizi içeren kişisel haftalık ders programınız e-posta ekleri (Excel ve Takvim) ile birlikte tarafınıza iletilecektir. Bu e-posta yalnızca yukarıda belirtilen programdaki dersleriniz hakkında ön bilgilendirme amaçlıdır.
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
