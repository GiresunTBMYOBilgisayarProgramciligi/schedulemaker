[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [AssetManager](./README.md) / **addCss**

---
# AssetManager::addCss(string $path, array $attributes = [])

Sisteme yeni bir stil dosyası eklemek için kullanılır.

## Mantık (Algoritma)
1.  **Yol Kontrolü**: Verilen `$path` değerinin halihazırda yüklü olan `$css` dizisinde olup olmadığına bakılır.
2.  **Mükerrerlik Denetimi**: Eğer dosya yolu dizi içinde bulunursa, işlem sonlandırılır (aynı dosya iki kez eklenmez).
3.  **Kayıt**: Eğer dosya yeni ise; dosya yolu ve varsa eklenen öznitelikler (`integrity`, `crossorigin` vb.) `$css` dizisine bir alt dizi olarak eklenir.
