[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **checkOverlap**

---
# ScheduleController::checkOverlap(string $start1, string $end1, string $start2, string $end2)

İki zaman aralığının çakışıp çakışmadığını Boolean olarak döndüren temel yardımcı fonksiyondur.

## Mantık
`(Start1 < End2) AND (Start2 < End1)`

Bu algoritma, matematiksel olarak iki aralığın herhangi bir noktasında örtüşme olup olmadığını en performanslı şekilde bulur.

## Parametreler
*   Zamanlar `HH:mm` veya `HH:mm:ss` formatında string olarak verilir. Karşılaştırma öncesi normalize edilir.

## Dönüş Değeri
*   `bool`: Çakışma varsa `true`, yoksa `false`.
