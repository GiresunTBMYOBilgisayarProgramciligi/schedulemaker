[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **escapeIcsText**

---
# ImportExportManager::escapeIcsText(string $text)

ICS (Takvim) dosyasının sözdizimine (syntax) zarar verebilecek özel karakterleri güvenli hale getirir.

## Mantık (Algoritma)
1.  **Karakter Değişimi**:
    - Ters eğik çizgi (`\`) -> `\\`
    - Virgül (`,`) -> `\,`
    - Noktalı virgül (`;`) -> `\;`
    - Yeni satır -> `\n`
2.  **Dönüş**: Kaçırılmış (escaped) güvenli metni döndürür.
