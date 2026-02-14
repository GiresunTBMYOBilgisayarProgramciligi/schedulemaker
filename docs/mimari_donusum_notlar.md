# Mimari Dönüşüm - Önemli Notlar

## Tasarım Kararları

### 1. Tam ORM Kullanımı

**Karar:** Repository katmanında raw SQL yerine Model'in metodları kullanılacak.

**Gerekçe:**
- ✅ **Tutarlılık:** Tüm kod aynı pattern'i takip eder
- ✅ **Hook Desteği:** Model'deki `beforeDelete()`, `afterCreate()` gibi hook'lar çalışır
- ✅ **Otomatik Loglama:** Model CRUD işlemleri otomatik loglanır
- ✅ **Transaction Yönetimi:** Model transaction'ları handle eder
- ✅ **Bakım Kolaylığı:** Tek bir yerden davranış değiştirilebilir

**Örnek:**
```php
// ❌ ÖNCE (Raw SQL)
public function deleteBatch(array $itemIds): int {
    $sql = "DELETE FROM schedule_items WHERE id IN (...)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($itemIds);
    return $stmt->rowCount();
}

// ✅ SONRA (ORM)
public function deleteBatch(array $itemIds): int {
    $deleted = 0;
    foreach ($itemIds as $id) {
        $item = $this->find($id);
        if ($item) {
            $item->delete(); // Hook'lar çalışır, loglanır
            $deleted++;
        }
    }
    return $deleted;
}
```

**Performans Notu:**
- Batch delete nadir kullanılır (cleanup işlemlerinde)
- Asıl kritik path `saveScheduleItems` - orada diğer optimizasyonlar var
- Gerekirse gelecekte sadece bu metodlar için raw SQL optimizasyonu yapılabilir

---

### 2. Feature Flag Yaklaşımı

**Karar:** Test ortamında basit on/off switch, production'da rollback için.

**Kullanım:**
```sql
-- Settings tablosunda
INSERT INTO settings (name, value) VALUES ('use_new_schedule_service', '0');

-- Yeni sisteme geçiş
UPDATE settings SET value = '1' WHERE name = 'use_new_schedule_service';

-- Sorun olursa geri dön
UPDATE settings SET value = '0' WHERE name = 'use_new_schedule_service';
```

**Controller'da:**
```php
public function saveScheduleItems(array $items) {
    if (FeatureFlags::useNewScheduleService()) {
        $service = new ScheduleService();
        return $service->saveScheduleItems($items);
    } else {
        return $this->oldSaveScheduleItems($items); // Eski kod korunur
    }
}
```

**Avantajlar:**
- ✅ Hata olursa 1 saniyede eski sisteme dön (sadece flag değiştir)
- ✅ Deployment sırasında risk yok
- ✅ Yeni sistem test edilirken eski sistem çalışır durumda

---

## Katman Sorumlulukları

### Repository Katmanı
**Sorumluluklar:**
- ✅ Veri erişimi (CRUD)
- ✅ Karmaşık query'ler (batch, join)
- ✅ Model kullanımı (raw SQL değil)

**Sorumlu DEĞİL:**
- ❌ İş mantığı
- ❌ Validation
- ❌ Transaction yönetimi (Service'in sorumluluğu)

### Service Katmanı
**Sorumluluklar:**
- ✅ İş mantığı
- ✅ Transaction başlatma/commit/rollback
- ✅ Validation çağrısı
- ✅ Repository orchestration
- ✅ Event dispatching (ileride)

**Sorumlu DEĞİL:**
- ❌ HTTP request/response
- ❌ View rendering
- ❌ Direct database access

### Controller Katmanı
**Sorumluluklar:**
- ✅ HTTP request parsing
- ✅ Response formatting (JSON, HTML)
- ✅ Authorization kontrolü
- ✅ Service method çağrısı

**Sorumlu DEĞİL:**
- ❌ İş mantығı
- ❌ Database access
- ❌ Validation logic

---

## Migration Stratejisi

### Faz 1: Altyapı ✅ (TAMAMLANDI)
- Base sınıflar
- Exception hierarchy
- Validation infrastructure

### Faz 2: İlk Service (Devam Ediyor)
- ScheduleService basit versiyon
- Feature flag entegrasyonu
- Parallel testing

### Faz 3-6: (Gelecek)
- Diğer service'ler
- Model refactoring
- Type safety (Enum'lar)

---

## Kod Standartları

### 1. DTO Kullanımı
```php
// ✅ Type-safe, immutable
readonly class ScheduleItemData {
    public function __construct(
        public int $scheduleId,
        public string $startTime,
        // ...
    ) {}
}

// Kullanım
$dto = ScheduleItemData::fromArray($requestData);
$service->save($dto);
```

### 2. Validation
```php
// ✅ Service'e girmeden önce validate et
$validator = new ScheduleItemValidator();
$result = $validator->validate($data);

if (!$result->isValid) {
    throw new ValidationException(
        'Validation failed',
        $result->errors
    );
}
```

### 3. Exception Handling
```php
// ✅ Context bilgisi taşı
throw new ScheduleConflictException(
    message: 'Çakışma var',
    conflictingItem: $item,
    schedule: $schedule
);

// Controller'da yakala
try {
    $service->save($items);
} catch (ScheduleConflictException $e) {
    return $this->response->json([
        'error' => true,
        'message' => $e->getMessage(),
        'context' => $e->getContext()
    ]);
}
```

---

## Test Stratejisi

### Unit Test
```php
class ScheduleItemValidatorTest {
    public function test_invalid_time_range() {
        $validator = new ScheduleItemValidator();
        $result = $validator->validate([
            'start_time' => '10:00',
            'end_time' => '09:00' // Geçersiz
        ]);
        
        $this->assertFalse($result->isValid);
        $this->assertContains('Başlangıç < Bitiş', $result->errors);
    }
}
```

### Integration Test
```php
class ScheduleServiceTest {
    public function test_save_creates_records() {
        $service = new ScheduleService();
        $result = $service->saveScheduleItems([...]);
        
        $this->assertNotEmpty($result->createdIds);
        $this->assertDatabaseHas('schedule_items', ['id' => $result->createdIds[0]]);
    }
}
```

---

## Bilinen Sorunlar ve TODO'lar

### TODO
- [ ] ScheduleService tek bir metot olarak başlayacak (`saveScheduleItems`)
- [ ] Diğer controller metodları (delete, update) daha sonra eklenecek
- [ ] Performance benchmark testleri yapılacak
- [ ] Error handling standardize edilecek

### Bilinen Limitasyonlar
- Şu an sadece ScheduleService'e odaklanıyoruz
- Diğer controller'lar (Lesson, User, Classroom) daha sonra
- Event system henüz yok (Faz 3'te)

---

## Kaynaklar

- [Mimari Dönüşüm Planı](file:///home/sametatabasch/PhpstormProjects/schedulemaker/docs/mimari_donusum_plani.md)
- [Algoritma Dokümantasyonu](file:///home/sametatabasch/PhpstormProjects/schedulemaker/docs/ders_programi_algoritmasi.md)
- Faz 1 Walkthrough: [walkthrough.md](file:///home/sametatabasch/.gemini/antigravity/brain/5965b4a4-0a38-4f33-ba59-bbe603013c34/walkthrough.md)
