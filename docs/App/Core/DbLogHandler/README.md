[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **DbLogHandler**

---
# App\Core\DbLogHandler

`DbLogHandler`, Monolog kütüphanesi için özelleştirilmiş bir handler sınıfıdır. Sistemde oluşan logları veritabanındaki `logs` tablosuna yazmaktan sorumludur.

## Temel İşlevler

1.  **Veritabanı Entegrasyonu**: Log kayıtlarını `PDO` üzerinden veritabanına aktarır.
2.  **Otomatik Tablo Yönetimi**: Eğer `logs` tablosu mevcut değilse, ilk log kaydı sırasında otomatik olarak oluşturur.
3.  **Hiyerarşik Loglama**: Sadece `DEBUG` veya `INFO` ve üzeri seviyedeki logları veritabanına yazar.

## Metodlar

*   [__construct()](./__construct.md): Handler nesnesini ve log seviyesini ilklendirir.
*   [write()](./write.md): Tekil bir log kaydını veritabanına yazar.
