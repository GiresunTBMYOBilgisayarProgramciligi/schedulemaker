[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **ImportExportManager**

---
# ImportExportManager

`ImportExportManager` sınıfı, sistemdeki verilerin (kullanıcılar, dersler, programlar) Excel formatında içe aktarılması (import) ve Excel/ICS formatlarında dışa aktarılması (export) işlemlerini yönetir.

## Temel Görevi
Karmaşık veri yapılarını (örneğin ders programı tablosu) anlamlı dosya formatlarına dönüştürmek ve harici dosyaları sistemin anlayabileceği Model yapılarına çevirmektir.

## Metod Listesi

### İçe Aktarma (Import)
*   [prepareImportFile()](./prepareImportFile.md): Yüklenen Excel dosyasını işleme hazırlar.
*   [importUsersFromExcel()](./importUsersFromExcel.md): Kullanıcıları sistem aktarır.
*   [importLessonsFromExcel()](./importLessonsFromExcel.md): Dersleri ve programları sisteme aktarır.

### Dışa Aktarımı (Export)
*   [prepareExportFile()](./prepareExportFile.md): Excel çıktı dosyasını ilklendirir.
*   [exportSchedule()](./exportSchedule.md): Ders programını Excel olarak üretir.
*   [exportScheduleIcs()](./exportScheduleIcs.md): Ders programını ICS (Takvim) olarak üretir.
*   [createFileTitle()](./createFileTitle.md): Çıktı dosyası için açıklayıcı bir başlık üretir.
*   [downloadExportFile()](./downloadExportFile.md): Üretilen dosyayı tarayıcıya indirilebilir olarak gönderir.

### Yardımcı ve Dahili Metodlar
*   [__construct()](./__construct.md): Spreadsheet ve dosya verilerini ilklendirir.
*   [generateScheduleFilters()](./generateScheduleFilters.md): Gelen filtreleri SQL ve Excel başlıklarına çevirir.
*   [setExportLessonName()](./setExportLessonName.md): Çıktıda görünecek ders adını formatlar.
*   [escapeIcsText()](./escapeIcsText.md): ICS formatı için metindeki özel karakterleri temizler.
*   [logger()](./logger.md): İşlem loglarına erişim sağlar.
*   [logContext()](./logContext.md): İşlem bağlamını hazırlar.
