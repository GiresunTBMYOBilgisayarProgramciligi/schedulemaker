[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **createFileTitle**

---
# ImportExportManager::createFileTitle($filters)

İndirilebilir dosya için anlamlı ve benzersiz bir dosya adı (filename) üretir.

## Mantık (Algoritma)
1.  **Meta Veri Toplama**: Filtrelerden gelen program adı veya hoca adı bilgisini alır.
2.  **Temizlik**: Dosya adında sorun çıkarabilecek boşlukları, Türkçe karakterleri ve özel işaretleri standart karakterlere çevirir veya siler.
3.  **Zaman Damgası**: İsmin sonuna `y-m-d_H-i` formatında güncel tarihi ekleyerek versiyon çakışmasını önler.
4.  **Dönüş**: `.xlsx` veya `.ics` uzantılı dosya adını döndürür.
