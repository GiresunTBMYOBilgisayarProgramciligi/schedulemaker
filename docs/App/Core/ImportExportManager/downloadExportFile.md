[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **downloadExportFile**

---
# ImportExportManager::downloadExportFile($fileName = "schedule.xlsx")

Oluşturulan Excel dosyasını tarayıcıya "indirilebilir dosya" (attachment) olarak gönderir.

## Mantık (Algoritma)
1.  **Headers**: HTTP başlıklarını (Content-Type, Content-Disposition: attachment, Cache-Control) Excel formatına (`.xlsx`) uygun şekilde ayarlar.
2.  **Writer**: `Xlsx` yazıcısını (writer) Spreadsheet nesnesine bağlar.
3.  **Stream**: Dosya içeriğini standart çıktıya (output stream) yazarak transferi başlatır.
4.  **Duruş**: Dosya gönderimi tamamlandıktan sonra `exit()` ile scripti durdurur.
