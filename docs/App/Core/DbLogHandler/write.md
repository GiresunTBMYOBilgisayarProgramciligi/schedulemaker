[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [DbLogHandler](./README.md) / **write**

---
# DbLogHandler::write(LogRecord $record)

Gelen günlük (log) kaydını veritabanındaki `logs` tablosuna kalıcı olarak yazar.

## Mantık (Algoritma)
1.  **Bağlam Ayrıştırma**: `LogRecord` nesnesi içinden `context` (kullanıcı bilgisi, ip, url vb.) ve `extra` verilerini alır.
2.  **Düzleştirme (Flattening)**: Hiyerarşik olan context verilerini, veritabanı sütunlarına uygun hale getirir (örn: `$ctx['username']` -> `username`).
3.  **Hazırlık**: `INSERT INTO logs ...` SQL cümlesini `PDO::prepare` ile hazırlar.
4.  **Veri Dönüştürme**:
    - Dizi tipindeki verileri (trace, context, extra) `json_encode` ile metne çevirir.
    - Zaman damgası (`NOW()`) bilgisini ekler.
5.  **Yürütme**: Hazırlanan verileri veritabanına gönderir.
6.  **Hata Yönetimi**: Eğer veritabanına yazma sırasında bir hata oluşursa (örn: tablo silinmişse), sonsuz döngüden kaçınmak için hatayı sadece standart `error_log` (php error log) dosyasına yazar ve işlemi sessizce durdurur.
