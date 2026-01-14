[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **limit**

---
# Model::limit(int $limit)

Dönecek maksimum kayıt sayısını belirler.

## Mantık (Algoritma)
1.  **Değer Atama**: Gelen tam sayı değerini nesnenin `limit` özelliğine atar.
2.  **Build Aşaması**: Sorgu inşa edilirken (`buildQuery`) bu değer `LIMIT ?` olarak SQL'e eklenir.
3.  **Zincirleme**: `$this` döner.
