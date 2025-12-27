[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **exportSchedule**

---
# ImportExportManager::exportSchedule(array $filters)

Ders programlarını estetik bir Excel tablosu formatında üretir.

## Mantık (Algoritma)
1.  **Filtreleme**: `generateScheduleFilters()` ile hedef programa/hocaya/dersliğe ait tüm filtreleri (yarıyıl bazlı) hazırlar.
2.  **Başlık Oluşturma**: `createFileTitle()` ile üniversite adı, akademik yıl ve dönem bilgilerini Excel'in en üst satırlarına yazar ve hücreleri birleştirir.
3.  **Ders Programı Matrisi**: `ScheduleController` üzerinden her bir filtre için gün/saat bazlı bir veri matrisi alır.
4.  **Hücre Yazımı**: Matristeki her bir hücreyi kontrol eder:
    - **Boş Hücre**: Boş bırakılır.
    - **Tek Ders**: Ders adı ve hoca bilgisini hücreye yazar.
    - **Gruplu Dersler**: Birden fazla dersi alt alta (`\n`) gelecek şekilde aynı hücreye sığdırır.
5.  **Şekillendirme**: Hücreleri ortalar, metinleri kaydırır (`WrapText`), derslik türüne göre renklendirme (opsiyonel) ve kenarlıklar ekler.
6.  **İndirme**: Sütun genişliklerini içeriğe göre otomatik ayarlar ve dosyayı tarayıcıya `Xlsx` formatında gönderir.
