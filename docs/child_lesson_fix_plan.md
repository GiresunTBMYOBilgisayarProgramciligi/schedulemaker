# Child Lesson Saat Aşımı Fix - Implementation Planı

## Sorun Tanımı

**Mevcut Durum:**
- Parent ders: 4 saat/hafta
- Child ders: 2 saat/hafta
- Parent'a 4 saatlik program eklendiğinde → Child'a da 4 saat ekleniyor ❌
- Child ders saati aşımı oluyor
- **Tüm kayıtlar rollback ediliyor** (parent da dahil)

**Beklenen Durum:**
- Parent'a 4 saatlik program eklendiğinde → Child'a sadece 2 saat eklenmeli ✅
- Child'ın fazla olan 2 saati **otomatik silinmeli**
- Parent kaydı **korunmalı**

---

## Çözüm Stratejisi

### Seçenek 1: Prevention (Önleme)
Parent'a item eklenirken child'ın ne kadar saati kaldığını kontrol et, fazlasını ekleme.

**Sorunlar:**
- Multi-schedule kaydetme sırasında her owner için kaç saat ekleneceğini hesaplamak karmaşık
- Child lesson'lar parent ile birlikte kaydediliyor

### Seçenek 2: Smart Cleanup ✅ (Tercih Edilen)
Parent'a item ekle, sonra child'ın fazla saatlerini sil.

**Avantajlar:**
- Basit implementation
- Mevcut `checkLessonHourLimits()` metodunu genişletir
- Transaction içinde yapılır

---

## Implementation Detayları

### 1. `checkLessonHourLimits()` Güncellemesi

**Eski Davranış:**
```php
if ($lesson->remaining_size < 0) {
    throw new Exception("Ders saati aşımı!");  // Rollback
}
```

**Yeni Davranış:**
```php
if ($lesson->remaining_size < 0) {
    // Child lesson mı kontrol et
    if ($lesson->parent_id !== null) {
        // Child lesson → Fazla saatleri sil
        $this->cleanupExcessChildHours($lesson, $scheduleType);
    } else {
        // Normal lesson → Exception fırlat (mevcut davranış)
        throw new Exception("Ders saati aşımı!");
    }
}
```

### 2. Yeni Metot: `cleanupExcessChildHours()`

```php
/**
 * Child lesson'ın fazla olan schedule item'larını siler
 * 
 * Parent ders ile child ders saati farklı olduğunda, child'a parent kadar
 * saat eklenebilir. Bu durumda child'ın toplam saati aşılır. Bu metod,
 * child'ın fazla olan saatlerini son eklenenlerden başlayarak siler.
 * 
 * **Örnek:**
 * - Parent: 4 saat/hafta
 * - Child: 2 saat/hafta
 * - Parent'a 4 saatlik item eklendi → Child'a da 4 saat eklendi
 * - Child fazla: 2 saat
 * → Son eklenen 2 saatlik item'lar silinir
 * 
 * @param Lesson $childLesson Child lesson entity'si
 * @param string $scheduleType Schedule tipi ('lesson', 'midterm-exam', etc.)
 * @return void
 */
private function cleanupExcessChildHours(Lesson $childLesson, string $scheduleType): void
{
    $excessHours = abs($childLesson->remaining_size);
    
    $this->logger->warning("Child lesson hour limit exceeded, cleaning up excess hours", 
        $this->logContext([
            'lesson_id' => $childLesson->id,
            'lesson_name' => $childLesson->getFullName(),
            'excess_hours' => $excessHours,
            'schedule_type' => $scheduleType
        ])
    );
    
    // Bu child lesson'a ait tüm schedule'ları bul
    // (owner_type='lesson', owner_id=child_lesson_id)
    $childSchedules = $this->scheduleRepo->findBy([
        'owner_type' => 'lesson',
        'owner_id' => $childLesson->id,
        'type' => $scheduleType
    ]);
    
    if (empty($childSchedules)) {
        $this->logger->error("No schedules found for child lesson", $this->logContext([
            'lesson_id' => $childLesson->id
        ]));
        return;
    }
    
    // Her schedule'dan en son eklenen item'ları bul ve sil
    $totalDeleted = 0;
    $hoursToDelete = $excessHours;
    
    foreach ($childSchedules as $schedule) {
        if ($hoursToDelete <= 0) {
            break;
        }
        
        // En son eklenen item'ları bul (id DESC)
        $items = $this->itemRepo->findBy(
            ['schedule_id' => $schedule->id],
            ['id' => 'DESC']  // En yeniden başla
        );
        
        foreach ($items as $item) {
            if ($hoursToDelete <= 0) {
                break;
            }
            
            // Item'ın saat değerini hesapla
            $itemHours = $this->calculateItemHours($item, $scheduleType);
            
            // Item'ı sil
            $item->delete();
            $totalDeleted++;
            $hoursToDelete -= $itemHours;
            
            $this->logger->debug("Deleted excess child lesson item", $this->logContext([
                'item_id' => $item->id,
                'item_hours' => $itemHours,
                'remaining_to_delete' => max(0, $hoursToDelete)
            ]));
        }
    }
    
    $this->logger->info("Child lesson excess hours cleaned up", $this->logContext([
        'lesson_id' => $childLesson->id,
        'deleted_items' => $totalDeleted,
        'excess_hours' => $excessHours
    ]));
}
```

### 3. Helper Metot: `calculateItemHours()`

```php
/**
 * Schedule item'ın kaç saat olduğunu hesaplar
 * 
 * @param ScheduleItem $item
 * @param string $scheduleType 'lesson' veya 'exam'
 * @return int|float Saat değeri (lesson için saat, exam için kişi sayısı olabilir)
 */
private function calculateItemHours(ScheduleItem $item, string $scheduleType): float
{
    if ($scheduleType === 'lesson') {
        // Ders için: start_time ve end_time farkı
        $start = strtotime($item->start_time);
        $end = strtotime($item->end_time);
        $minutes = ($end - $start) / 60;
        return $minutes / 60; // Saat cinsinden (örn: 100 dakika = 1.67 saat)
    } else {
        // Sınav için: item başına 1 (veya assignment sayısı)
        return 1;
    }
}
```

---

## Akış Diyagramı

```
┌─────────────────────────────┐
│ Parent Item Kaydediliyor    │
│ (4 saat)                    │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│ Multi-Schedule Kaydetme     │
│ - Parent: 4 saat            │
│ - Child: 4 saat (aşım!)     │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│ checkLessonHourLimits()     │
│ Parent: OK ✅               │
│ Child: -2 saat ❌           │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│ parent_id var mı?           │
│ → VAR (Child Lesson)        │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│ cleanupExcessChildHours()   │
│ - Fazla: 2 saat             │
│ - Son 2 saatlik item'ı sil  │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│ Transaction Commit ✅        │
│ - Parent: 4 saat kaydedildi │
│ - Child: 2 saat kaydedildi  │
└─────────────────────────────┘
```

---

## Örnek Senaryo

### Başlangıç Durumu
```
Parent Ders (Algorithm):
- lesson_hour: 4
- Mevcut: 0 saat
- Kalan: 4 saat

Child Ders (Algorithm-Lab):
- lesson_hour: 2
- parent_id: Algorithm ID
- Mevcut: 0 saat
- Kalan: 2 saat
```

### İşlem
```
Parent'a Pazartesi 08:00-11:50 (4 saat) ekleniyor
→ Multi-schedule ile child'a da ekleniyor
```

### checkLessonHourLimits Sonucu
```
Parent:
- Toplam: 4 saat
- Eklenen: 4 saat
- Kalan: 0 saat ✅

Child:
- Toplam: 2 saat
- Eklenen: 4 saat
- Kalan: -2 saat ❌ (AŞIM!)
```

### cleanupExcessChildHours Çalışıyor
```
1. Child'ın schedule'larını bul
2. En son eklenen item'ları getir (id DESC)
3. 2 saatlik item'ları sil
4. Sonuç:
   - Child: 2 saat kaldı ✅
```

### Final Durum
```
Parent: 4 saat programı var ✅
Child: 2 saat programı var ✅ (fazlası temizlendi)
```

---

## Edge Cases

### Case 1: Birden Fazla Schedule
Child lesson'ın hem lesson schedule'ı hem program schedule'ı var.
→ Sadece lesson schedule'ından sil/kısalt (program schedule'ı dokunma)

### Case 2: Item Süresi Tam Bölünemiyor (Slot-Based)
**Duration: 50dk, Break: 10dk → 1 slot = 60dk**

Fazla: 2 slot (2 saat), Item: 3 slot (3 saat)
1. Option A: Item'ı 1 slot'a kısalt (end_time değiştir)
2. Option B: Item'ı sil, 1 slot'luk yeni item ekle

→ **Option A tercih edilir** (mevcut item korunur, sadece end_time güncellenir)

**Örnek:**
- Item: 08:00-10:50 (3 slot)
- Fazla: 2 slot → 1 slot kalmalı
- Sonuç: 08:00-08:50 (1 slot) ✅

### Case 3: Child'ın Hiç Schedule'ı Yok
Parent ekleniyor ama child schedule bulunamadı
→ Log warning, devam et (exception fırlatma)

---

## Risk Analizi

**Yüksek Risk:**
- Child'dan silinen item'lar başka schedule'larda da var mı?
  → VAR! Multi-schedule kaydetme nedeniyle aynı item 4 yerde
  → Solution: Sadece child lesson schedule'ından sil, diğerlerine dokunma

**Orta Risk:**
- Calculation hataları (saat hesaplama)
  → Solution: Detaylı loglama, test senaryoları

**Düşük Risk:**
- Performance (birden fazla delete)
  → Solution: Transaction içinde, normal sayıda item için sorun olmaz

---

## Testing

### Test Case 1: Basit Aşım
```php
$parent = Lesson::create(['lesson_hour' => 4]);
$child = Lesson::create(['lesson_hour' => 2, 'parent_id' => $parent->id]);

// Parent'a 4 saat ekle
addScheduleItem($parent, '08:00', '11:50'); // 4 saat

// Sonuç:
// Parent schedule: 4 saat ✅
// Child schedule: 2 saat ✅ (2 saat silindi)
```

### Test Case 2: Kısmi Aşım
```php
$child->lesson_hour = 3; // Parent: 4, Child: 3

// Parent'a 4 saat ekle
// Child'a 4 saat eklenir → 1 saat fazla
// 1 saatlik item silinir
```

### Test Case 3: Aşım Yok
```php
$child->lesson_hour = 4; // Parent: 4, Child: 4 (eşit)

// Parent'a 4 saat ekle
// Child'a 4 saat eklenir → Aşım yok
// Hiçbir şey silinmez ✅
```

---

## Implementation Adımları

1. **calculateItemHours()** helper metodunu ekle
2. **cleanupExcessChildHours()** metodunu implement et  
3. **checkLessonHourLimits()** metodunu güncelle
4. **Test et** (manual)
5. **Dökümanı güncelle** (`child_lesson_bug.md`)

---

## Gelecek İyileştirmeler

**v2:**
- Silme stratejisi: En son eklenen yerine en az değerli olanı sil
- Kullanıcı bildirimi: "Child lesson'dan X saat silindi" mesajı
- Item gruplaması: Aynı günde birden fazla item varsa gruplayarak sil

**v3:**
- Prevention: Parent eklenirken child'ın ne kadar boşluğu var kontrol et
- Smart allocation: Child'a sadece ihtiyacı kadar item ekle
