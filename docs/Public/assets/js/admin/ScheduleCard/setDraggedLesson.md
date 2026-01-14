[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **setDraggedLesson**

---
# ScheduleCard.setDraggedLesson(lessonElement, dragEvent)

Sürükleme işlemi başladığında, sürüklenen elemandaki verileri merkezi `draggedLesson` objesine aktarır.

## Mantık (Algoritma)
1.  **Sıfırlama**: `resetDraggedLesson()` ile eski sürükleme verilerini temizler.
2.  **Veri Aktarımı**: `getDatasetValue()` metodunu kullanarak sürüklenen HTML elementindeki `data-*` özniteliklerini (id, code, hours vb.) `this.draggedLesson` objesine kopyalar.
3.  **Başlangıç Noktası Tespiti**:
    - Sürüklenen eleman bir `<table>` içindeyse `start_element` değerini `"table"` yapar.
    - Eleman `.available-schedule-items` (liste) içindeyse `start_element` değerini `"list"` yapar.
4.  **Referans Saklama**: Sürüklenen HTML elementinin kendisini `this.draggedLesson.HTMLElement` olarak saklar.
