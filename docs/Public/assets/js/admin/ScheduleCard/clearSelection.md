[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **clearSelection**

---
# ScheduleCard.clearSelection()

Seçili olan tüm ders kartlarını temizler ve sistemi seçim yokmuş gibi eski haline döndürür.

## Mantık (Algoritma)
1.  **Görsel Temizlik**: `this.selectedLessonElements` içindeki her bir elementten `.selected-lesson` sınıfını kaldırır ve içlerindeki checkbox'ları `false` yapar.
2.  **Mantıksal Temizlik**: `selectedLessonElements` ve `selectedScheduleItemIds` set yapılarını tamamen boşaltır (`clear()`).
