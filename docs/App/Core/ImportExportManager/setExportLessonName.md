[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **setExportLessonName**

---
# ImportExportManager::setExportLessonName(Lesson $lesson, $scheduleType)

Dışa aktarma dosyasındaki (Excel/ICS) ders hücresinde görünecek metni, izleme yapılan schedule tipine göre (Ders, Hoca, Derslik) formatlar.

## Mantık (Algoritma)
1.  **Tip Kontrolü**:
    - **Hoca Programı**: Ders adının yanına şube (`group_no`) bilgisini ekler.
    - **Derslik Programı**: Ders adının yanına hoca ismini ekler.
    - **Program Programı**: Ders adının yanına hem hoca hem de derslik bilgisini ekler.
2.  **Dönüş**: İlgili bağlama göre zenginleştirilmiş ders adı metnini döndürür.
