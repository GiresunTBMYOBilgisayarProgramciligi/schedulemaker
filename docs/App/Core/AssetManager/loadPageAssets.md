[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [AssetManager](./README.md) / **loadPageAssets**

---
# AssetManager::loadPageAssets(string $page)

Önceden tanımlanmış sayfa grupları için toplu yükleme yapar.

## Mantık (Algoritma)
1.  **Grup Kontrolü**: `$pageAssets` dizisi içerisinde verilen `$page` anahtarı aranır.
2.  **CSS Yükleme**: Eğer ilgili sayfa için `css` grubu tanımlanmışsa, bu gruptaki her bir dosya yolu için `addCss()` metodu çağrılır.
3.  **JS Yükleme**: Eğer ilgili sayfa için `js` grubu tanımlanmışsa, bu gruptaki her bir dosya yolu için `addJs()` metodu çağrılır.
4.  **Hata Yönetimi**: Eğer sayfa anahtarı bulunamazsa, sessizce işlem tamamlanır (herhangi bir ekleme yapılmaz).
