<?php
/**
 * @var $code int HTTP durum kodu
 * @var $message string Hata mesajı
 * @var $file string Hatanın oluştuğu dosya (Opsiyonel - Debug modu)
 * @var $line int Hatanın oluştuğu satır (Opsiyonel - Debug modu)
 * @var $trace string Hata izleme yığını (Opsiyonel - Debug modu)
 */

$title = "Hata " . $code;
$color = "danger";
$icon = "bi bi-exclamation-triangle-fill";

if ($code == 404) {
    $title = "Sayfa Bulunamadı";
    $color = "warning";
    $icon = "bi bi-exclamation-triangle";
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Hata Sayfası</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Anasayfa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Hata
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="error-page">
                <h2 class="headline text-<?php echo $color; ?>"> <?php echo $code; ?></h2>

                <div class="error-content">
                    <h3><i class="<?php echo $icon; ?> text-<?php echo $color; ?>"></i> Oops! Bir şeyler ters gitti.
                    </h3>

                    <p>
                        <?php echo htmlspecialchars($message); ?>
                        <br>
                        Bu hatayı düzeltmek için çalışacağız. Bu sırada <a href="/admin">panoya dönebilirsiniz</a>.
                    </p>

                    <?php if (isset($file) && $_ENV['DEBUG'] === 'true'): ?>
                        <div class="card card-<?php echo $color; ?> card-outline mt-4">
                            <div class="card-header">
                                <h3 class="card-title">Hata Detayları (Debug)</h3>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-2">Dosya:</dt>
                                    <dd class="col-sm-10"><?php echo htmlspecialchars($file); ?></dd>

                                    <dt class="col-sm-2">Satır:</dt>
                                    <dd class="col-sm-10"><?php echo $line; ?></dd>

                                    <dt class="col-sm-12">Yığın İzi (Trace):</dt>
                                    <dd class="col-sm-12">
                                        <pre class="bg-light p-3 border rounded"
                                            style="overflow-x: auto; max-height: 400px;"><code><?php echo htmlspecialchars($trace); ?></code></pre>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</main>