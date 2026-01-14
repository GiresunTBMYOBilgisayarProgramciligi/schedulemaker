[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **highlightUnavailableCells**

---
# ScheduleCard.highlightUnavailableCells(draggedLesson)

Ders sürüklenirken, o dersin yerleştirilemeyeceği (kısıtlı veya dolu) hücreleri kırmızıyla vurgular.

## Mantık (Algoritma)
1.  **Veri Toplama**: Sürüklenen dersin hoca tercihleri (`availability`), derslik kısıtları ve halihazırda sistemde kayıtlı olan diğer ders verilerini analiz eder.
2.  **Hücre Taraması**: Tablodaki tüm hücreleri (`<td>`) tek tek döner.
3.  **Kural Denetimi**:
    - Hoca o saatte "meşgul" veya "tercih etmiyor" olarak işaretlenmişse,
    - Hücre başka bir ders tarafından tamamen doldurulmuşsa,
    - Dersin süresi (saat sayısı) kalan boşluğa sığmıyorsa,
4.  **Vurgulama**: Uygun olmayan her hücreye `.unavailable-cell` CSS sınıfını ekler.
