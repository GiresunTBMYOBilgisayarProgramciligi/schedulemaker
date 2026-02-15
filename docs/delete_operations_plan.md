# Delete Operations - Implementation Planı

## Amaç

Multi-schedule kaydetme ile eklenen item'ların, silinirken de tüm schedule'lardan temizlenmesini sağlamak.

**Mevcut Durum:**
- `ScheduleController::deleteScheduleItems()` - L1689-1893
- Sibling-based silme (aynı item'ın tüm schedule kopyalarını buluyor)
- Parçalama mantığı (item'ın sadece bir kısmını silme)
- Child lesson desteği

**Hedef:** Bu mantığı ScheduleService'e taşımak

---

## Temel Kavramlar

### 1. Sibling Items (Kardeş Item'lar)

Aynı ders item'ının farklı schedule'lardaki kopyaları:

```
Item: Pazartesi 09:00-10:50, Algorithm
Siblings:
- ID 45: Program schedule (Bilgisayar Programcılığı)
- ID 46: Lesson schedule (Algorithm)
- ID 47: User schedule (Ahmet Hoca)
- ID 48: Classroom schedule (A101)
```

**Kritik:** Bir item silindiğinde, tüm siblings silinmeli!

### 2. Partial Delete (Kısmi Silme)

Bir item'ın sadece belirli zaman dilimini veya belirli derslerini silme:

**Örnek 1: Zaman Dilimi Silme**
```
Mevcut: 08:00-10:50 (3 slot: Algorithm)
Sil: 09:00-10:50 (2 slot)
Sonuç: 08:00-08:50 (1 slot kaldı)
```

**Örnek 2: Group Item'dan Ders Silme**
```
Mevcut: 08:00-10:50 (Algorithm + Calculus)
Sil: Algorithm'i çıkar
Sonuç: 08:00-10:50 (Sadece Calculus)
```

### 3. Flatten Timeline Approach

Item'ları slot bazlı parçalara ayırarak silme işlemi:

```
Original Item: 08:00-10:50
Slots: [08:00-08:50] [09:00-09:50] [10:00-10:50]

Delete: 09:00-09:50
Result: [08:00-08:50] ❌ DELETED ❌ [10:00-10:50]
→ 2 yeni item oluşturulur
```

---

## Implementation Adımları

### Adım 1: DTO ve Result Sınıfları

#### DeleteScheduleResult (DTO)

```php
// App/DTOs/DeleteScheduleResult.php

namespace App\DTOs;

class DeleteScheduleResult
{
    public bool $success;
    public array $deletedIds;      // Silinen item ID'leri
    public array $createdIds;      // Partial delete sonucu oluşan item ID'leri
    public array $errors;
    public int $totalDeleted;
    public int $totalCreated;

    public static function success(
        array $deletedIds,
        array $createdIds = []
    ): self {
        $result = new self();
        $result->success = true;
        $result->deletedIds = $deletedIds;
        $result->createdIds = $createdIds;
        $result->errors = [];
        $result->totalDeleted = count($deletedIds);
        $result->totalCreated = count($createdIds);
        return $result;
    }

    public static function failure(string $error): self {
        $result = new self();
        $result->success = false;
        $result->deletedIds = [];
        $result->createdIds = [];
        $result->errors = [$error];
        $result->totalDeleted = 0;
        $result->totalCreated = 0;
        return $result;
    }
}
```

### Adım 2: ScheduleService - Delete Metotları

#### 2.1. Ana Delete Metodu

```php
/**
 * Schedule item'ları siler (multi-schedule aware)
 * 
 * **Sibling-Based Delete:**
 * Bir item silindiğinde, aynı item'ın tüm schedule kopyaları (siblings) da silinir.
 * Bu sayede multi-schedule kaydetme ile eklenen item'lar tutarlı şekilde silinir.
 * 
 * **Partial Delete:**
 * - Zaman dilimi silme: Item'ın belirli saatlerini sil
 * - Ders silme: Group item'dan belirli dersleri çıkar
 * 
 * **expandGroup Parametresi:**
 * - true: Child lesson'lu bir dersi sildiğinde, parent + tüm child'ları sil
 * - false: Sadece belirtilen dersi sil (child/parent ilişkisini koru)
 * 
 * @param array $itemsData Silinecek item'lar [['id' => 45, 'start_time' => '09:00', ...], ...]
 * @param bool $expandGroup Child lesson grubu genişletilsin mi?
 * @return DeleteScheduleResult
 */
public function deleteScheduleItems(
    array $itemsData,
    bool $expandGroup = true
): DeleteScheduleResult;
```

#### 2.2. Sibling Bulma Metotları

```php
/**
 * Ders item'ı için sibling'leri bulur
 * 
 * Bir ders item'ının siblings'i:
 * - Aynı ders, aynı zaman dilimi
 * - Farklı schedule'larda (program, lesson, user, classroom)
 * 
 * @param ScheduleItem $baseItem
 * @param array $lessonIds İlgili ders ID'leri
 * @return array ScheduleItem[]
 */
private function findSiblingItems(
    ScheduleItem $baseItem,
    array $lessonIds
): array;

/**
 * Sınav item'ı için sibling'leri bulur
 * 
 * Sınav sibling mantığı daha karmaşık:
 * - Assignments içindeki gözlemci ve derslikler
 * - Child lesson'lar
 * 
 * @param ScheduleItem $baseItem
 * @return array ScheduleItem[]
 */
private function findExamSiblingItems(ScheduleItem $baseItem): array;
```

#### 2.3. Partial Delete İşleyicisi

```php
/**
 * Item'ı flatten timeline yaklaşımı ile siler/parçalar
 * 
 * ** Flatten Timeline:**
 * Item'ı slot'lara böl, silinecek bölümleri çıkar, kalan parçaları birleştir
 * 
 * **Örnek:**
 * ```
 * Item: 08:00-10:50 (Algorithm)
 * Delete: 09:00-09:50
 * Steps:
 * 1. Slot'lara böl: [08:00-08:50] [09:00-09:50] [10:00-10:50]
 * 2. Ortadakini çıkar: [08:00-08:50] ❌ [10:00-10:50]
 * 3. Yeni item'lar: [08:00-08:50] ve [10:00-10:50]
 * ```
 * 
 * @param ScheduleItem $item İşlenecek item
 * @param array $deleteIntervals Silinecek zaman dilimleri [['start' => '09:00', 'end' => '09:50'], ...]
 * @param array $targetLessonIds Silinecek ders ID'leri (partial lesson delete için)
 * @param bool $deleteOriginal false ise item silinmez (sadece parçalama simüle edilir)
 * @return array ['deleted' => bool, 'created' => ScheduleItem[]]
 */
private function processItemDeletion(
    ScheduleItem $item,
    array $deleteIntervals,
    array $targetLessonIds = [],
    bool $deleteOriginal = true
): array;
```

---

## İşlem Akışı

### Akış 1: Basit Silme (Tüm Item)

```
1. USER: Item 45'i sil (09:00-10:50, Algorithm)

2. ScheduleService::deleteScheduleItems([45])
   │
   ├─→ findSiblingItems(45) 
   │   Returns: [45, 46, 47, 48] (program, lesson, user, classroom)
   │
   ├─→ DELETE all siblings
   │   - Item 45: ❌ Deleted
   │   - Item 46: ❌ Deleted
   │   - Item 47: ❌ Deleted
   │   - Item 48: ❌ Deleted
   │
   └─→ Return DeleteScheduleResult([45, 46, 47, 48], [])
```

### Akış 2: Partial Delete (Zaman Dilimi)

```
1. USER: Item 45'in 09:00-09:50 kısmını sil

2. ScheduleService::deleteScheduleItems([{id: 45, start: '09:00', end: '09:50'}])
   │
   ├─→ findSiblingItems(45)
   │   Returns: [45, 46, 47, 48]
   │
   ├─→ DELETE all siblings
   │
   ├─→ processItemDeletion for each sibling
   │   Original: 08:00-10:50
   │   Delete: 09:00-09:50
   │   Creates: [08:00-08:50] and [10:00-10:50]
   │
   ├─→ CREATE 2 new items for each schedule
   │   - 8 yeni item (4 schedule x 2 parça)
   │
   └─→ Return DeleteScheduleResult([45,46,47,48], [101,102,103,104,105,106,107,108])
```

### Akış 3: Group Item'dan Ders Silme

```
1. USER: Group item'dan Algorithm'i sil (Calculus kalsın)

2. ScheduleService::deleteScheduleItems([{id: 45, lesson_id: 502}])
   │
   ├─→ findSiblingItems(45, [502])
   │   Returns: [45, 46, 47, 48]
   │
   ├─→ DELETE all siblings
   │
   ├─→ processItemDeletion for each sibling
   │   Original data: [Algorithm, Calculus]
   │   Delete lesson_id: 502 (Algorithm)
   │   New data: [Calculus]
   │
   ├─→ CREATE 1 new item for each schedule
   │   - 4 yeni item (sadece Calculus ile)
   │
   └─→ Return DeleteScheduleResult([45,46,47,48], [101,102,103,104])
```

---

## Kritik Noktalar

### 1. Sibling Bulma Hassasiyeti

**Sorun:** Yanlış sibling tespiti, alakasız item'ların silinmesine sebep olur.

**Çözüm:**
- Zaman çakışması kontrolü (overlap check)
- Aynı akademik yıl/dönem/tip
- Owner matching (program, lesson, user, classroom)

### 2. Duplicate Entry Önleme

**Sorun:** Sibling'leri tek tek silip yenisini oluştururken duplicate entry hatası.

**Çözüm mevcut kodda:**
```php
// 1. Önce TÜM sibling'leri sil
foreach ($siblings as $sibling) {
    $sibling->delete();
}

// 2. Sonra yenilerini oluştur (çakışma riski yok)
foreach ($siblings as $sibling) {
    $result = processItemDeletion($sibling, ...);
    // Yeni item oluştur
}
```

### 3. Child Lesson Group Expansion

**expandGroup = true:**
```
Delete: Algorithm (parent)
→ Parent + tüm child'ları silinir
→ Veritabanı Lab (child)'ı da silinir
```

**expandGroup = false:**
```
Delete: Algorithm (parent)
→ Sadece Algorithm silinir
→ Veritabanı Lab korunur
```

**Kullanım:**
- `wipeResourceSchedules()` → `expandGroup = false` (child'ları koru)
- Normal silme → `expandGroup = true` (tüm grubu sil)

### 4. Preferred/Unavailable Handling

**Preferred item silme:**
- Eğer item'da ders varsa: Dersi sil, preferred alan geri kazanılır
- Eğer item boşsa: Tüm item silinir

```php
// Örnek: Preferred slot'ta ders var
Original: 09:00-09:50 (preferred=true, data=[Algorithm])
Delete: Algorithm

Result: 09:00-09:50 (status='preferred', data=[])
```

---

## Test Senaryoları

### Test 1: Basit Ders Silme
```php
// Pazartesi 09:00-10:50, Algorithm
$result = $service->deleteScheduleItems([['id' => 45]]);

// Expected:
// - 4 item silinir (program, lesson, user, classroom)
// - Yeni item oluşturulmaz
assertEquals(4, count($result->deletedIds));
assertEquals(0, count($result->createdIds));
```

### Test 2: Partial Zaman Silme
```php
// 08:00-10:50'den 09:00-09:50'yi sil
$result = $service->deleteScheduleItems([
    ['id' => 45, 'start_time' => '09:00', 'end_time' => '09:50']
]);

// Expected:
// - 4 item silinir
// - 8 item oluşturulur (4 schedule x 2 parça)
assertEquals(4, count($result->deletedIds));
assertEquals(8, count($result->createdIds));
```

### Test 3: Child Lesson Expand
```php
// Algorithm (parent) + Veritabanı-Lab (child)
$result = $service->deleteScheduleItems(
    [['id' => 45, 'lesson_id' => 100]], // Algorithm
    $expandGroup = true
);

// Expected:
// - Algorithm ve Veritabanı-Lab her ikisi de silinir
// - Her ders için 4 schedule × 2 ders = 8 sibling
assertEquals(8, count($result->deletedIds));
```

---

## Migration Path

### Faz 1: DTO ve BaseService Ekleme
1. `DeleteScheduleResult` DTO'su
2. Helper metotlar (calculateItemSlots, vb.)

### Faz 2: Delete Metotlarını Taşıma
1. `findSiblingItems()` → ScheduleService
2. `findExamSiblingItems()` → ScheduleService
3. `processItemDeletion()` → ScheduleService
4. `deleteScheduleItems()` → ScheduleService

### Faz 3: Controller Entegrasyon
1. `ScheduleController::deleteScheduleItems()` güncelle → Service çağır
2. Feature flag ile kontrol
3. Logging entegrasyonu

### Faz 4: Test ve Doğrulama
1. Manuel test senaryoları
2. Edge case'ler
3. Performance test

---

## Dikkat Edilecek Noktalar

**1. Slot Hesaplaması:**
- Duration ve break değerleri settings'den alınmalı
- Farklı schedule tipleri (lesson/exam) için farklı değerler

**2. Logging:**
- Silme işlemleri detaylı loglanmalı
- Hangi item'lar silindiği, hangilerinin oluşturulduğu

**3. Transaction Yönetimi:**
- Tüm silme işlemi transaction içinde
- Hata durumunda rollback

**4. Performance:**
- Çok sayıda sibling item için batch delete düşünülebilir
- N+1 query problemine dikkat

---

## Sonraki Adımlar

1. ✅ Plan onayı
2. DeleteScheduleResult DTO'su oluştur
3. findSiblingItems metotlarını taşı
4. processItemDeletion metodunu taşı
5. deleteScheduleItems ana metodunu implement et
6. Controller entegrasyonu
7. Test
