# Schedule Table Partial Bileşenleri

Schedule tablo görünümlerinde kullanılan ortak partial bileşenler.

**Dizin:** `App/Views/admin/pages/schedules/partials/components/`

## Bileşenler

### `_childLessons.php`

Bağlı dersleri (child lessons) gösteren partial.

**Beklenen değişkenler:**
- `$slotData` — Slot verisi (`lesson->childLessons` ilişkisi yüklü olmalı)

**Çıktı:** Eğer bağlı dersler varsa "Bağlı Dersler" başlığı ve her bağlı dersin tam adını (program, grup, sınıf bilgileri dahil) listeler. Tekrarlayan isimler `array_unique` ile filtrelenir.

**Kullanım:**
```php
<?php include __DIR__ . '/components/_childLessons.php'; ?>
```

---

### `_emptySlot.php`

Boş (ders atanmamış) slot render'ı. Status'e göre CSS sınıfı alır ve gerektiğinde açıklama popover'ı gösterir.

**Beklenen değişkenler:**
- `$scheduleItem` — ScheduleItem nesnesi
- `$preference_mode` — Tercih modunda ise checkbox gösterilir

**Özellikler:**
- `slot-unavailable`, `slot-preferred` gibi CSS sınıflarını `getSlotCSSClass()` ile alır
- Tercih modunda `draggable="true"` olur ve toplu seçim checkbox'ı gösterilir
- `detail['description']` varsa Bootstrap popover ile açıklama notu gösterir

**Kullanım:**
```php
<?php include __DIR__ . '/components/_emptySlot.php'; ?>
```

## Dosya Yapısı

```
App/Views/admin/pages/schedules/partials/
├── examScheduleTable.php          # Sınav programı tablosu
├── lessonScheduleTable.php        # Ders programı tablosu
├── availableLessons.php           # Mevcut dersler listesi
├── scheduleCard.php               # Schedule kart bileşeni
└── components/
    ├── _childLessons.php          # Bağlı dersler (ortak)
    └── _emptySlot.php             # Boş slot (ortak)
```
