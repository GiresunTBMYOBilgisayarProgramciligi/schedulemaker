[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Controller](./README.md) / **getListByFilters**

---
# Controller::getListByFilters(?array $filters = null)

Veritabanından belirli kriterlere uyan tüm kayıtları bir dizi Model nesnesi olarak çeker.

## Mantık (Algoritma)
1.  **Model Hazırlığı**: Alt sınıfın belirttiği `$modelName` (örn: `User`) üzerinden yeni bir model nesnesi oluşturur.
2.  **Query Builder**: Modelin `get()` metodunu çağırarak sorgu oluşturucuyu aktif eder.
3.  **Filtreleme**: `$filters` dizisini `where()` koşulu olarak sorguya ekler.
4.  **Veri Çekme**: `all()` metodunu tetikleyerek veritabanından sonuçları çeker ve `Model` nesnesi tipinde bir dizi döndürür.
