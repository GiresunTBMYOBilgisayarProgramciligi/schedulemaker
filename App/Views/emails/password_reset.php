<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Şifre Sıfırlama</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <h2 style="color: #0d6efd;">Merhaba <?= htmlspecialchars($user->name) ?>,</h2>
        <p>Hesabınız için şifre sıfırlama isteği aldık. Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz.</p>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="<?= htmlspecialchars($resetLink) ?>" style="background-color: #0d6efd; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Şifremi Sıfırla</a>
        </p>

        <p>Eğer butona tıklayamıyorsanız, aşağıdaki bağlantıyı tarayıcınızın adres çubuğuna kopyalayıp yapıştırabilirsiniz:</p>
        <p style="word-break: break-all; color: #6c757d;"><a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a></p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #999;">Bu şifre sıfırlama bağlantısının süresi 60 dakika içinde dolacaktır.<br>
        Eğer şifre sıfırlama isteğinde bulunmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
    </div>
</body>
</html>
