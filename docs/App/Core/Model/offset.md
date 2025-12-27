[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **offset**

---
# Model::offset(int $offset)

Sorgu sonuçlarının kaçıncı kayıttan itibaren döneceğini belirler. Genellikle sayfalama (pagination) işlemlerinde `limit` ile birlikte kullanılır.

## Mantık (Algoritma)
1.  **Değer Atama**: Gelen `$offset` değerini nesnenin `offset` özelliğine kaydeder.
2.  **Sorgu İnşası**: `buildQuery` esnasında bu değer `OFFSET ?` olarak SQL'e eklenir.
3.  **Zincirleme**: Diğer metodlar için `$this` döner.
