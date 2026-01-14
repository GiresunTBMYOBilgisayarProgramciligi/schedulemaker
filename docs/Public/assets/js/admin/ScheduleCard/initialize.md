[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **initialize**

---
# ScheduleCard.initialize(scheduleCardElement)

DOM elementinden verileri okuyarak nesneyi tam işlevsel hale getirir ve olay dinleyicilerini (event listeners) bağlar.

## Mantık (Algoritma)
1.  **Veri Okuma**: `dataset` üzerinden `scheduleId`, `duration` ve `break` değerlerini okur.
2.  **Asenkron Veri Çekme**: `getSchedule()` metodunu çağırarak programın detaylarını (akademik yıl, dönem, sahibi vb.) sunucudan alır ve `this` nesnesine kopyalar.
3.  **Element Eşleştirme**: `.available-schedule-items` (liste) ve `table.active` (tablo) elementlerini bulur.
4.  **Olay Dinleyicileri (Drag & Drop)**:
    - `draggable="true"` olan tüm elemanlara `dragstart` dinleyicisi ekler.
    - `.drop-zone` sınıfına sahip alanlara `drop` ve `dragover` dinleyicilerini bağlar.
5.  **Yardımcı Sistemler**:
    - `initStickyHeaders()` ile yapışkan başlıkları başlatır.
    - `initBulkSelection()` ile toplu seçim sistemini aktif eder.
    - `initContextMenu()` ile ders kartları için sağ tık menüsünü aktif eder.
