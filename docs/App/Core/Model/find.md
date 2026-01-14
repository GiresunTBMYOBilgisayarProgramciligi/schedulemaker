[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **find**

---
# Model::find($id)

Bir kaydı birincil anahtarı (ID) üzerinden hızlıca bulur.

## Mantık (Algoritma)
1.  **Filtreleme**: `where(['id' => $id])` koşulunu sorguya ekler.
2.  **Yürütme**: `first()` metodunu çağırarak veritabanından tek bir nesne çeker.
3.  **Dönüş**: Kayıt bulunursa nesne, bulunamazsa `null` döner.
