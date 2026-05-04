[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **getLessonItemData**

---
# ScheduleCard.getLessonItemData(element)

Verilen bir HTML elementinden (ders kartı) veritabanı işlemleri için gerekli olan veri paketini hazırlar.

## Mantık (Algoritma)
1.  **Hücre Tespiti**: Elementin içinde bulunduğu en yakın tablo hücresini (`<td>`) bulur.
2.  **Koordinat Okuma**: Hücrenin `dataset.dayIndex` ve `dataset` (start_time, end_time) bilgilerini kullanarak zaman koordinatlarını belirler.
3.  **Özellik Toplama**: Elementin `dataset` değerinden `lesson_id`, `lecturer_id`, `classroom_id` ve `group_no` gibi bilgileri okur.
4.  **Paketleme**: Tüm bu bilgileri sunucu tarafındaki `ScheduleItem` modeline uygun bir nesne yapısında birleştirerek döndürür.
