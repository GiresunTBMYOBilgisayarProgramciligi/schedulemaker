[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **prepareExportFile**

---
# ImportExportManager::prepareExportFile()

Dışa aktarılacak Excel dosyası için temel şablonu (Sheet, fontlar, başlıklar) hazırlar.

## Mantık (Algoritma)
1.  **Aktif Sayfa**: Mevcut Spreadsheet nesnesindeki aktif çalışma sayfasını (`ActiveSheet`) seçer.
2.  **Stil**: Yazı tipi (Arial/Inter), boyut ve genel hücre biçimlendirmelerini ayarlar.
