[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **__construct**

---
# Model::__construct()

Yeni bir model nesnesi oluşturulduğunda veritabanı bağlantısını ve temel bileşenleri hazır hale getirir.

## Mantık (Algoritma)
1.  **DB Bağlantısı**: `Database::getConnection()` üzerinden paylaşımlı PDO bağlantısını `$this->database` özelliğine atar.
2.  **Initial State**: Modelin içindeki veri alanlarını (`data`) ve sorgu parçalarını (`query_parts`) boşaltır.
