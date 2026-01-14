[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **logContext**

---
# ImportExportManager::logContext(array $extra = [])

Import/Export işlemleri için standart veri bağlamı üretir.

## Mantık (Algoritma)
1.  **Bağlantı**: `Log::context($this, $extra)` metodunu çağırır.
2.  **Özellik**: Hangi dosyanın işlendiği, hangi kullanıcı tarafından yapıldığı ve işlemin hangi aşamada olduğu bilgilerini içeren bir dizi döndürür.
