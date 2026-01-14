[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Router](./README.md) / **logContext**

---
# Router::logContext(array $extra = [])

Yönlendirme sırasında log kaydına eklenecek bağlam verilerini hazırlar.

## Mantık (Algoritma)
1.  **Bağlantı**: `Log::context($this, $extra)` metodunu çağırır.
2.  **Kapsam**: Mevcut istekteki kullanıcı, IP, URL ve hangi Router metodunun çalıştığı bilgisini içeren bir dizi döndürür.
