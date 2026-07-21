<?php
/**
 * @var $code int HTTP durum kodu
 * @var $message string Hata mesajı
 * @var $file string Hatanın oluştuğu dosya (Opsiyonel - Debug modu)
 * @var $line int Hatanın oluştuğu satır (Opsiyonel - Debug modu)
 * @var $trace string Hata izleme yığını (Opsiyonel - Debug modu)
 */

$errorData = [
    400 => ['color' => 'warning', 'title' => 'Geçersiz İstek.', 'message' => 'İsteğiniz anlaşılamadı veya geçersiz formatta.'],
    403 => ['color' => 'danger', 'title' => 'Erişim Reddedildi.', 'message' => 'Bu sayfayı görüntülemek veya bu işlemi yapmak için yetkiniz bulunmamaktadır.'],
    404 => ['color' => 'warning', 'title' => 'Sayfa Bulunamadı.', 'message' => 'Aradığınız sayfayı bulamadık. URL\'yi yanlış yazmış olabilirsiniz veya sayfa kaldırılmış olabilir.'],
    409 => ['color' => 'danger', 'title' => 'Çakışma Meydana Geldi.', 'message' => 'İsteğiniz, sunucudaki mevcut durumla çakıştığı için tamamlanamadı.'],
    422 => ['color' => 'warning', 'title' => 'İşlenemeyen İçerik.', 'message' => 'Gönderdiğiniz veriler işlenemedi veya kurallara uymuyor.'],
    500 => ['color' => 'danger', 'title' => 'Sunucu Tarafında Bir Hata Oluştu.', 'message' => 'Sunucu beklenmedik bir durumla karşılaştı. Lütfen daha sonra tekrar deneyin.']
];

$code = $code ?? 500;
$currentError = $errorData[$code] ?? $errorData[500];

// Eğer exception'dan gelen orijinal mesaj sadece "Internal Server Error" gibi genel bir metin değilse ve boş değilse onu kullan, 
// aksi takdirde bizim Türkçe varsayılan mesajımızı kullan
$displayMessage = (!empty($message) && strlen(trim($message)) > 0) ? $message : $currentError['message'];
?>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height: 100vh; margin: 0; padding: 2rem 0;">
    <div class="text-center px-4 w-100">
        <h1 class="display-1 fw-bold text-<?= $currentError['color'] ?> mb-3" style="font-size: 6rem;"><?= $code ?></h1>
        <h2 class="fs-3 mb-3"><?= $currentError['title'] ?></h2>
        <p class="text-muted mb-4 mx-auto" style="max-width: 600px;">
            <?php echo htmlspecialchars($displayMessage); ?>
            <br>
            Lütfen tekrar deneyin veya sorun devam ederse destek ile iletişime geçin.
        </p>
        <div class="mb-5">
            <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/admin') ?>" class="btn btn-primary me-2"><i class="bi bi-arrow-left me-2"></i> Geri</a>
            <a href="#" class="btn btn-outline-secondary"><i class="bi bi-life-preserver me-2"></i> İletişime geç</a>
        </div>
        
        <?php if (isset($file) && $_ENV['DEBUG'] === 'true'): ?>
            <div class="card card-<?= $currentError['color'] ?> card-outline text-start mt-5 mx-auto shadow-sm" style="max-width: 900px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-bug me-2"></i>Hata Detayları (Debug)</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-2 text-truncate">Dosya:</dt>
                        <dd class="col-sm-10 text-break"><?php echo htmlspecialchars($file); ?></dd>

                        <dt class="col-sm-2">Satır:</dt>
                        <dd class="col-sm-10"><?php echo $line; ?></dd>

                        <dt class="col-sm-12 mt-3">Yığın İzi (Trace):</dt>
                        <dd class="col-sm-12 mb-0 mt-2">
                            <pre class="bg-light p-3 border rounded mb-0 text-dark" style="overflow-x: auto; max-height: 500px; font-size: 0.875rem;"><code><?php echo htmlspecialchars($trace); ?></code></pre>
                        </dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
