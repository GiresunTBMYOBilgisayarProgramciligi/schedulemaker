[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **prepareImportFile**

---
# ImportExportManager::prepareImportFile(array $uploadedFile)

Sunucuya yüklenen bir Excel dosyasının varlığını ve okunabilirliğini kontrol eder.

## Mantık (Algoritma)
1.  **Varlık Kontrolü**: Dosyanın `tmp_name` yolunun mevcut olup olmadığını denetler.
2.  **Okuma**: `IOFactory::load()` kullanarak dosyayı bellek üzerinde bir Spreadsheet nesnesine dönüştürür.
3.  **Hata Yönetimi**: Eğer dosya bozuksa veya format desteklenmiyorsa istisna fırlatır.
