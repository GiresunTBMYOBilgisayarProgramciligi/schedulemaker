[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **initBulkSelection**

---
# ScheduleCard.initBulkSelection()

Ders kartları üzerinde toplu işlem yapabilmek için gerekli checkbox ve tıklama olaylarını (event) dinler.

## Mantık (Algoritma)
1.  **Checkbox Dinleyicisi**: Kartlardaki `.lesson-bulk-checkbox` değiştiğinde `updateSelectionState()` metodunu çağırarak kartın seçili durumunu günceller.
2.  **Tek Tıklama (Single Click)**:
    - Ders kartına tıklandığında (ve tıklanan eleman bir link değilse), kartın içindeki checkbox'ı tersine çevirir (toggle).
    - Checkbox'ın `change` olayını manuel tetikleyerek seçimi işletir.
3.  **Çift Tıklama (Double Click)**:
    - Bir ders kartına çift tıklandığında, aynı `lesson_id` değerine sahip (aynı isimdeki) TÜM kartları otomatik olarak seçili hale getirir.
    - Metin seçilmesini engellemek için tarayıcı seçimlerini temizler.
