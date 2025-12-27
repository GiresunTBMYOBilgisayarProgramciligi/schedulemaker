[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **logger**

---
# Model::logger()

Model bazlı işlemlerin (kayıt silme, güncelleme hataları vb.) loglanması için merkezi logger nesnesine erişim sağlar.

## Mantık (Algoritma)
1.  **Bağlantı**: `Log::logger()` metodunu çağırır.
2.  **Singleton**: Sistemde tek bir Monolog örneği (`app` kanalı) üzerinden çalışır.
3.  **Dönüş**: `Monolog\Logger` nesnesini döndürür.
