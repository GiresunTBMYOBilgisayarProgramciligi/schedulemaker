[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **generateScheduleItems**

---
# ScheduleCard.generateScheduleItems(lessons, targetCell)

Seçili dersleri veya tekil bir dersi, tabloya yerleştirilmeye uygun bir veri yapısına dönüştürür.

## Mantık (Algoritma)
1.  **Girdi Analizi**: Parametre olarak gelen dersleri (tekil veya dizi) standart bir listeye dönüştürür.
2.  **Koordinat Tespiti**: `targetCell` (hedef hücre) üzerinden gün ve başlangıç saati bilgilerini okur.
3.  **Obje Oluşturma**: Her ders için şu alanları içeren bir `ScheduleItem` objesi üretir:
    - `lesson_id`, `program_id`, `lecturer_id`
    - `day`, `start_time`, `duration`
    - `classroom_id` (varsa), `observer_id` (varsa)
4.  **Toplu İşlem**: Birden fazla ders seçiliyse (`bulk selection`), hepsini aynı konuma (veya ardışık saatlere) yerleştirmek üzere bir dizi döndürür.
