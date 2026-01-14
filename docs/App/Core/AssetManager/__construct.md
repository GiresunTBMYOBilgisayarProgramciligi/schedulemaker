[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [AssetManager](./README.md) / **__construct**

---
# AssetManager::__construct()

Sınıf ilklendirildiğinde temel (global) varlıkları yükler.

## Mantık (Algoritma)
1.  **CSS İlklendirme**: Sınıf içindeki `$globalCss` dizisinde tanımlı olan temel stil dosyaları (Fonts, AdminLTE core CSS vb.) ana `$css` listesine aktarılır.
2.  **JS İlklendirme**: Sınıf içindeki `$globalJs` dizisinde tanımlı olan temel kütüphaneler (Bootstrap, AdminLTE core JS vb.) ana `$js` listesine aktarılır.
