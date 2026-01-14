[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **clearCells**

---
# ScheduleCard.clearCells()

Tablo hücrelerindeki tüm geçici görsel vurguları (çakışma uyarıları, sürükleme ipuçları vb.) temizler.

## Mantık (Algoritma)
1.  Tablodaki tüm hücreleri seçer.
2.  `.unavailable-cell`, `.drag-over`, `.crash-warning` gibi tüm operasyonel CSS sınıflarını hücrelerden kaldırır.
3.  Genellikle `dragEnd` veya `drop` olaylarından sonra tabloyu temiz bir duruma getirmek için kullanılır.
