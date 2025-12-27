[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **logContext**

---
# Model::logContext(array $extra = [])

Model işlemlerinde log kaydına otomatik olarak eklenecek bağlam (context) verilerini hazırlar.

## Mantık (Algoritma)
1.  **Bağlantı**: `Log::context($this, $extra)` metodunu çağırır.
2.  **Otomatik Veriler**:
    - İşlemi yapan kullanıcı (`user_id`, `name`).
    - Hangi model/tablo (`table_name`) üzerinde işlem yapıldığı.
    - Hangi sınıf ve fonksiyon içinden çağrıldığı (debug_backtrace).
    - IP ve URL bilgileri.
3.  **Dönüş**: Log nesnesine parametre olarak verilecek birleşik diziyi döndürür.
