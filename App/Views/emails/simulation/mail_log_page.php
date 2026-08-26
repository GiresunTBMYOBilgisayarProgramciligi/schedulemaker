<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-posta Simülasyon & Test Logları</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: system-ui, -apple-system, sans-serif; }
        .email-content { background: #fff; max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <div>
                <h2 class="h4 mb-0 text-primary"><i class="bi bi-envelope-paper-heart"></i> E-posta Simülasyon & Test Logları</h2>
                <small class="text-muted">Bu sayfadaki e-postalar kullanıcılara gönderilmemiş, test amaçlı yerel olarak yakalanmıştır.</small>
            </div>
            <div>
                <a href="/admin/publishschedule" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left"></i> Yayınlama Sayfasına Dön</a>
                <button onclick="clearLogs()" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Logları Temizle</button>
            </div>
        </div>
        <div id="mail_entries">
            <!-- NEW_ENTRIES_HERE -->
        </div>
    </div>
    <script>
        async function clearLogs() {
            if (!confirm('Tüm mail loglarını temizlemek istediğinize emin misiniz?')) return;
            try {
                const res = await fetch('/ajax/clearMailLogs', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.msg || 'Temizleme başarısız.');
                }
            } catch(e) {
                alert('İletişim hatası.');
            }
        }
    </script>
</body>
</html>
