[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Services](../README.md) / **TimelineManager**

---

# TimelineManager

`TimelineManager`, zaman çizelgesi (timeline) üzerindeki işlemlerden sorumlu bir yardımcı servistir.

## Metotlar

### getTimeSlots(string $scheduleType)

Sistem ayarlarına göre (`day_start`, `day_end`, `duration`, `break`) zaman dilimlerini (slots) oluşturur.

**Parametreler:**
- `$scheduleType`: Program tipi (`lesson`, `midterm-exam`, `final-exam`, `makeup-exam`).

**Döndürdüğü Değer:**
- `array`: Zaman dilimleri listesi.
  ```php
  [
    ['start' => '08:00', 'end' => '08:50'],
    ['start' => '09:00', 'end' => '09:50'],
    ...
  ]
  ```

---

### flattenTimeline(array $items, string $newStart, string $newEnd, string $scheduleType = 'lesson')

Verilen program item'larını çakışan saatleri birleştirerek flatten eder.

---

### findFreeSlots(array $items, string $scheduleType = 'lesson')

Verilen gün için boş zaman dilimlerini bulur.
