[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **constructor**

---
# ScheduleCard.constructor(scheduleCardElement = null)

Sınıfın yeni bir örneğini oluşturur ve temel özellik (property) değerlerini varsayılan halleriyle ilklendirir.

## Mantık (Algoritma)
1.  **Özellik İlklendirme**: `this.card`, `this.id`, `this.table`, `this.list` gibi tablo ve liste elementlerini tutan referansları `null` olarak ayarlar.
2.  **Meta Veri Hazırlığı**: `this.academic_year`, `this.semester`, `this.type` gibi program bağlamını belirleyen alanları hazırlar.
3.  **Sürükle-Bırak Durumu**: `this.draggedLesson` objesi içinde sürüklenen dersin tüm meta verilerini (`lesson_id`, `lecturer_id`, `day_index` vb.) sıfırlar.
4.  **Seçim Yönetimi**: Toplu işlem için kullanılan `this.selectedLessonElements` ve `this.selectedScheduleItemIds` Set yapılarını oluşturur.
5.  **Otomatik Başlatma**: Eğer bir `scheduleCardElement` (DOM elementi) verilmişse, `initialize()` metodunu çağırarak verileri bu elementten okur.
