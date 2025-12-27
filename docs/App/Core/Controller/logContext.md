[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Controller](./README.md) / **logContext**

---
# Controller::logContext(array $extra = [])

Kontrolcü katmanındaki loglar için standart veri bağlamı üretir.

## Mantık (Algoritma)
1.  **Bağlantı**: `Log::context($this, $extra)` metodunu çağırır.
2.  **Özellik**: Hangi kontrolcü (class) ve hangi işlemin (method) yapıldığı bilgisini otomatik olarak toplar.
