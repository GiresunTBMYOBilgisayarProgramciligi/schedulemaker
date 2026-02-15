# Child Lesson Saat Hesaplama Bug'ı

## Sorun

**Ana ders ile çocuk dersin ders saatleri farklı olduğunda**, çocuk derse ders saatinden fazla program saati ekleniyor.

## Örnek Senaryo

```
Ana Ders: 4 saat/hafta
Çocuk Ders: 2 saat/hafta
Problem: Çocuğa 4 saat program ekleniyor ❌
Beklenen: Çocuğa 2 saat program eklenmeli ✅
```

## Çözüm (LessonService'de)
daha sonra planlanacak

## Test Case

```php
$parent = Lesson::create(['lesson_hour' => 4]);
$child = Lesson::create(['lesson_hour' => 2]);
// Child'a item ekle -> schedule_hours = 2 olmalı (4 DEĞİL!)
```

## Çözüm Durumu

- [x] ScheduleService'de fix edildi
- [x] `checkLessonHourLimits()` child lesson kontrolü eklendi
- [x] `cleanupExcessChildHours()` metodu implement edildi
- [x] Slot-based silme/kısaltma desteği
- [ ] Test case yazılacak (manual test yapılabilir)

## Implementation Detayları

**Dosya:** `App/Services/ScheduleService.php`

**Yeni Metotlar:**
- `cleanupExcessChildHours()` - Child'ın fazla saatlerini sil/kısalt
- `calculateItemSlots()` - Item'ın kaç slot olduğunu hesapla
- `calculateNewEndTime()` - Slot bazlı yeni end_time hesapla

**Davranış:**
- Normal ders aşımı → Exception fırlat (eski davranış)
- Child lesson aşımı → Fazla saatleri otomatik temizle (yeni davranış)
