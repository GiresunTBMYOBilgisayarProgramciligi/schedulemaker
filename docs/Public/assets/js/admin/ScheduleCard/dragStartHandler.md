[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dragStartHandler**

---
# ScheduleCard.dragStartHandler(e)

Bir ders kartı sürüklenmeye başladığında tetiklenen olay yöneticisidir.

## Mantık (Algoritma)
1.  **Hedef Tespiti**: Sürüklenen elementin bir ders kartı (`.lesson-card`) olduğundan emin olur.
2.  **Veri Hazırlığı**: `setDraggedLesson()` metodunu çağırarak sürüklenen kartın verilerini (`id`, `hours`, `type` vb.) merkezi `draggedLesson` objesine kaydeder.
3.  **Görsel Efekt**: Sürüklenen elemana `.dragging` CSS sınıfını ekleyerek şeffaflık veya farklı bir stil kazandırır.
4.  **Veri Transferi**: Tarayıcının `DataTransfer` objesine dersin ID bilgisini ekler.
5.  **Otomatik Seçim**: Eğer sürüklenen kart seçili değilse, mevcut seçimi temizler (`clearSelection`) ve sadece bu kartı seçili hale getirir.
6.  **Çakışma Önizleme**: Sürüklenen dersin yerleşemeyeceği (çakışma olan) hücreleri görsel olarak işaretlemek için `highlightUnavailableCells()` metodunu tetikler.
