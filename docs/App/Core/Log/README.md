[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Log**

---
# Log

`Log` sınıfı, uygulamanın merkezi günlük tutma (logging) asistanıdır. Monolog kütüphanesini kullanarak hataları, sistem olaylarını ve kullanıcı aktivitelerini standart bir formatta kaydeder.

## Temel Görevi
Uygulama genelinde paylaşılan tek bir Logger örneği sağlar ve log mesajlarına eklenecek olan bağlam (context) verilerini (kullanıcı ID, IP, URL, Dosya/Satır vb.) otomatik olarak hazırlar.

## Metodlar
*   [logger()](./logger.md): Monolog logger nesnesini static olarak döndürür.
*   [context()](./context.md): Log mesajları için standart bağlam verilerini (user, ip, file vb.) hazırlar.
