[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / **ScheduleController**

---
## Temel Çalışma Mantığı ve Kavramlar

### Status (Durum) Türleri
`schedule_items` tablosundaki `status` alanı, bir zaman bloğunun davranışını belirler:

*   **`single`**: Standart, tekil ders. Sert çakışma hatası fırlatır.
*   **`group`**: Birleştirilebilir ders. Farklı programların ortak aldığı dersler için kullanılır. "Flatten Timeline" mantığıyla çalışır.
*   **`preferred`**: Tercih edilen saat. Üzerine gerçek ders bindiğinde otomatik olarak bölünür veya silinir.
*   **`unavailable`**: Kapalı saat. Kesinlikle ders eklenemez (Hard conflict).

Ana algoritmalar şunlardır:
1.  **Zaman Çizelgesi Düzleştirme (Flatten Timeline)**: Silme ve grup birleştirme işlemlerinde zamanı atomik parçalara bölerek yönetir.
2.  **Çakışma Çözümleme (Conflict Resolution)**: Standart, grup ve tercih edilen (`preferred`) zaman dilimleri arasındaki öncelikleri belirler.

## Metod Listesi (İçindekiler)

### Yardımcı ve Hazırlık Metodları
*   [__construct()](./__construct.md)
*   [generateEmptyWeek()](./generateEmptyWeek.md)
*   [lessonHourToMinute()](./lessonHourToMinute.md)
*   [getLessonNameFromItem()](./getLessonNameFromItem.md)
*   [generateTimesArrayFromText()](./generateTimesArrayFromText.md)

### Görünüm ve Veri Hazırlama
*   [prepareScheduleRows()](./prepareScheduleRows.md)
*   [prepareScheduleCard()](./prepareScheduleCard.md)
*   [getSchedulesHTML()](./getSchedulesHTML.md)
*   [createScheduleExcelTable()](./createScheduleExcelTable.md)

### Kayıt ve Güncelleme Mantığı
*   [saveScheduleItems()](./saveScheduleItems.md)
*   [processGroupItemSaving()](./processGroupItemSaving.md)
*   [resolvePreferredConflict()](./resolvePreferredConflict.md)

### Çakışma ve Uygunluk Kontrolleri
*   [checkScheduleCrash()](./checkScheduleCrash.md)
*   [checkItemConflict()](./checkItemConflict.md)
*   [checkOverlap()](./checkOverlap.md)
*   [resolveConflict()](./resolveConflict.md)
*   [availableLessons()](./availableLessons.md)
*   [availableClassrooms()](./availableClassrooms.md)
*   [availableObservers()](./availableObservers.md)

### Silme Mantığı
*   [deleteScheduleItems()](./deleteScheduleItems.md)
*   [findSiblingItems()](./findSiblingItems.md)
*   [processItemDeletion()](./processItemDeletion.md)
