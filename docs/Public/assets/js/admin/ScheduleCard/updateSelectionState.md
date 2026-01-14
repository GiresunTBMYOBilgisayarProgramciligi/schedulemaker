[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **updateSelectionState**

---
# ScheduleCard.updateSelectionState(lessonCard, isSelected)

Tekil bir ders kartının seçili olup olmadığını görsel ve mantıksal olarak günceller.

## Mantık (Algoritma)
1.  **Görsel Güncelleme**: Seçiliyse (`isSelected: true`), karta `.selected-lesson` CSS sınıfını ekler; aksi halde çıkarır.
2.  **Mantıksal Güncelleme**:
    - Seçiliyse: Kart elementini `this.selectedLessonElements` setine, `schedule_item_id` bilgisini `this.selectedScheduleItemIds` setine ekler.
    - Değilse: Bu verileri ilgili set yapılarından siler.
