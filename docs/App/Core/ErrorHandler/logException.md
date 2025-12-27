[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **logException**

---
# ErrorHandler::logException($exception)

Yakalanan bir istisnayı, sistemin loglama standartlarına uygun şekilde kaydeder.

## Mantık (Algoritma)
1.  **Logger Erişimi**: `Log::logger()` üzerinden merkezi loglama nesnesini alır.
2.  **Kullanıcı Tespiti**: Hatanın hangi kullanıcı işleminde oluştuğunu anlamak için `UserController` üzerinden aktif oturumdaki kullanıcıyı (`name`, `id`) tespit etmeye çalışır.
3.  **İstek Analizi**: Mevcut `REQUEST_URI` (URL) ve `REMOTE_ADDR` (IP) bilgilerini toplar.
4.  **Trace (İz) Çıkarma**: İstisnanın fırlatıldığı sınıf, metod, dosya ve satır bilgisini `getTrace()` ve `getFile()` ile ayıklar.
5.  **Kayıt**: Tüm bu verileri yapılandırılmış bir dizi (context) içinde `error` seviyesinde log sistemine gönderir.
6.  **Fallback**: Eğer loglama işlemi sırasında bir hata oluşursa (veritabanı kapalıysa vb.), son çare olarak PHP'nin standart `error_log()` fonksiyonuyla sistem dosyasına yazar.
