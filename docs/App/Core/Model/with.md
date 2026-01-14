[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **with**

---
# Model::with(array|string $relations)

Eager Loading (ön yükleme) yaparak ana model ile birlikte ilişkili olduğu diğer modellerin de çekilmesini sağlar.

## Mantık (Algoritma)
1.  **Formatlama**: Parametre string ise diziye çevirir.
2.  **Kayıt**: Yüklenmesi istenen ilişki isimlerini (örn: `lessons`, `department`) dahili bir listede toplar.
3.  **Yükleme Tetikleyicisi**: `all()` veya `first()` metodları çalıştıktan sonra `loadRelations()` metodu bu listeyi kullanarak ek sorguları çalıştırır.
4.  **Zincirleme**: `$this` döner.
