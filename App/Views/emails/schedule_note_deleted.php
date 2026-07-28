<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ders Programı Notunuz Silindi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .header { background: #dc3545; color: #ffffff; padding: 15px; text-align: center; border-radius: 6px 6px 0 0; }
        .content { padding: 20px 10px; line-height: 1.6; }
        .note-box { background: #fff5f5; border-left: 4px solid #dc3545; padding: 12px; margin-top: 15px; font-style: italic; color: #555; }
        .footer { font-size: 12px; color: #777; text-align: center; margin-top: 20px; border-top: 1px solid #eeeeee; padding-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0;">Program Notunuz Silindi</h2>
    </div>
    <div class="content">
        <p>Sayın <strong><?= htmlspecialchars($lecturer->getFullName()) ?></strong>,</p>
        <p>
            <strong><?= htmlspecialchars($note->academic_year) ?> - <?= htmlspecialchars($note->semester) ?></strong> (<?= htmlspecialchars($note->schedule_type) ?>) dönemi için sisteme eklemiş olduğunuz ders/sınav programı notu <strong><?= htmlspecialchars($deletedBy->getFullName()) ?></strong> tarafından silinmiştir.
        </p>

        <div class="note-box">
            <strong>Silinen Not Metni:</strong><br>
            <?= nl2br(htmlspecialchars($note->note)) ?>
        </div>

        <p style="margin-top: 20px;">
            Gerekli gördüğünüz durumlarda kullanıcı paneliniz üzerinden yeni bir program notu veya kısıt isteği iletebilirsiniz.
        </p>
    </div>
    <div class="footer">
        Bu e-posta otomatik olarak oluşturulmuştur. Lütfen doğrudan yanıtlamayınız.<br>
        &copy; <?= date('Y') ?> Schedule Maker
    </div>
</div>
</body>
</html>
