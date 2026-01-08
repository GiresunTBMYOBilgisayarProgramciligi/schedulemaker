[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **renderErrorView**

---
# ErrorHandler::renderErrorView($view, $exception, $statusCode)

Kullanıcıya görsel bir hata sayfası (HTML) sunar.

## Mantık (Algoritma)
1.  **Temizlik**: `ob_end_clean` ile tamponlanmış önceki tüm çıktıları yok eder (temiz sayfa).
2.  **HTTP Status**: `http_response_code` ile tarayıcıya 404, 500 gibi uygun durum kodunu bildirir.
3.  **Veri Hazırlama**: İstisna mesajını ve hata kodunu bir diziye pakatler. Eğer `DEBUG` modu aktifse dosya yolu ve stack trace bilgisini de ekler. (Ayrıca görünüm içerisinde de `DEBUG` kontrolü yapılmaktadır).
4.  **Bağımlılık İlklendirme**: Hata sayfasının (theme.php) çalışması için gerekli olan `UserController` ve `AssetManager` nesnelerini manuel olarak oluşturur.
5.  **View Render**: `View` sınıfı üzerinden `admin/errors/error.php` şablonunu yükleyerek kullanıcıya gösterir.
6.  **Duruş**: Sayfa basıldıktan sonra `exit()` ile scriptin çalışmasını kesin olarak durdurur.
