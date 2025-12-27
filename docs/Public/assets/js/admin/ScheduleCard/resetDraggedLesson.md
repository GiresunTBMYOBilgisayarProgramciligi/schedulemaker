[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **resetDraggedLesson**

---
# ScheduleCard.resetDraggedLesson()

Sürükleme işlemi bittiğinde veya iptal edildiğinde sürüklenen ders bilgisini sıfırlar.

## Mantık (Algoritma)
1.  **Döngü**: `this.draggedLesson` objesindeki tüm anahtarları (lesson_id, hours, elements vb.) iterate eder.
2.  **Sıfırlama**: Her bir anahtara `null` değerini atayarak nesneyi bir sonraki sürükleme işlemi için hazır hale getirir.
