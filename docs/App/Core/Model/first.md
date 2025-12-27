[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **first**

---
# Model::first()

Sorgu sonucunda dönen ilk kaydı tek bir nesne olarak döndürür.

## Mantık (Algoritma)
1.  **Limit Atama**: Sorguya otomatik olarak `LIMIT 1` ekler.
2.  **Yürütme**: `all()` metodunu çağırarak veriyi çeker.
3.  **Sonuç**: Dönen dizinin ilk elemanını alır. Eğer sonuç boşsa `null` döner.
