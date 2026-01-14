[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **get**

---
# Model::get()

Query builder (sorgu oluşturucu) mekanizmasını ilklendirir ve zincirleme (chaining) işlemlerini başlatır.

## Mantık (Algoritma)
1.  **Sıfırlama**: Mevcut nesne üzerindeki `query_parts` (select, where, join vb.) dizisini temizler.
2.  **Referans**: Mevcut `$this` nesnesini döndürür.
3.  **Kullanım Amacı**: `select()`, `where()`, `limit()` gibi metodların çağrılabilmesi için temel oluşturur.
