# Mimari Dönüşüm Planı - Service Layer Pattern'e Geçiş

## Mevcut Durum Analizi

### Proje Yapısı
```
App/
├── Controllers/        # Controller katmanı (7 controller)
├── Models/            # Model katmanı (9 model)
├── Core/              # Framework çekirdeği
│   ├── Controller.php # Base controller
│   ├── Model.php      # Base model (Active Record pattern)
│   ├── Database.php   # PDO wrapper
│   └── ...
├── Policies/          # Authorization (Gate pattern)
├── Routers/           # Routing
├── Views/             # Presentation
└── Helpers/           # Utility fonksiyonlar
```

### Mevcut Mimari: MVC + Active Record

**Güçlü Yönler:**
- ✅ MVC pattern düzgün uygulanmış
- ✅ Active Record pattern ile basit CRUD
- ✅ Authorization (Policy/Gate) mevcut
- ✅ Logging infrastructure var
- ✅ Base sınıflar iyi tasarlanmış

**Sorunlu Alanlar:**
- ❌ İş mantığı Controller'larda (Fat Controllers)
- ❌ Service katmanı yok
- ❌ Repository pattern yok
- ❌ Validation dağınık
- ❌ Transaction yönetimi controller'da
- ❌ Test edilebilirliği düşük

---

## Hedef Mimari: Layered Architecture + Service Layer Pattern

### Hedef Yapı
```
App/
├── Controllers/           # Thin controllers (HTTP layer)
├── Services/             # İş mantığı katmanı (YENİ)
│   ├── ScheduleService.php
│   ├── LessonService.php
│   └── ...
├── Repositories/         # Veri erişim katmanı (YENİ)
│   ├── ScheduleRepository.php
│   ├── LessonRepository.php
│   └── ...
├── Models/               # Domain models (sadece veri)
├── DTOs/                 # Data Transfer Objects (YENİ)
│   ├── ScheduleItemData.php
│   └── ...
├── Validators/           # Validation katmanı (YENİ)
│   ├── ScheduleItemValidator.php
│   └── ...
├── Exceptions/           # Custom exceptions (YENİ)
│   ├── ScheduleException.php
│   └── ...
├── Enums/                # Type-safe enums (YENİ)
│   ├── ScheduleItemStatus.php
│   └── ...
├── Events/               # Event system (İLERİDE)
├── Policies/             # Authorization (MEVCUT)
└── Core/                 # Framework (MEVCUT)
```

### Katman Sorumlulukları

**1. Controller Layer (HTTP)**
- Request parsing
- Response formatting
- Service method çağrısı
- Authorization kontrolü
- Sadece HTTP concern'leri

**2. Service Layer (İş Mantığı)**
- İş kuralları
- Transaction yönetimi
- Service orchestration
- Event dispatching
- Domain logic

**3. Repository Layer (Veri Erişimi)**
- Database queries
- Model mapping
- Cache yönetimi
- Query optimization

**4. Model Layer (Domain)**
- Sadece veri
- Basit getter/setter
- Relationship tanımları

---

## Neden Bu Mimari?

### 1. Separation of Concerns (SoC)
Her katman tek sorumluluğa sahip, değişiklikler izole edilmiş.

### 2. Testability
- Unit test: Service layer (business logic)
- Integration test: Repository layer
- E2E test: Controller layer

### 3. Reusability
Service'ler farklı controller'lardan, CLI'dan, queue job'lardan kullanılabilir.

### 4. Maintainability
İş mantığı tek yerde, kod tekrarı minimized.

### 5. Scalability
Her katman bağımsız optimize edilebilir.

---

## 6 Fazlı Migration Planı

> **İlke:** Hiçbir zaman mevcut çalışan kod bozulmadan, adım adım geçiş yapılacak.

### Faz 1: Altyapı Hazırlığı (1 Hafta)
**Amaç:** Yeni katmanlar için gerekli infrastructure oluştur.

#### 1.1. Klasör Yapısı Oluştur
```bash
mkdir -p App/Services
mkdir -p App/Repositories
mkdir -p App/DTOs
mkdir -p App/Validators
mkdir -p App/Exceptions
mkdir -p App/Enums
```

#### 1.2. Base Sınıflar Oluştur

**App/Services/BaseService.php**
```php
<?php
namespace App\Services;

use PDO;
use App\Core\Database;
use Monolog\Logger;
use App\Core\Log;

abstract class BaseService {
    protected PDO $db;
    protected Logger $logger;
    
    public function __construct() {
        $this->db = Database::getConnection();
        $this->logger = Log::logger();
    }
    
    protected function beginTransaction(): void {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }
    
    protected function commit(): void {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }
    
    protected function rollback(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
```

**App/Repositories/BaseRepository.php**
```php
<?php
namespace App\Repositories;

use PDO;
use App\Core\Database;
use App\Core\Model;

abstract class BaseRepository {
    protected PDO $db;
    protected string $modelClass;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function find(int $id): ?Model {
        $model = new $this->modelClass;
        return $model->find($id);
    }
    
    public function findBy(array $criteria): array {
        $model = new $this->modelClass;
        return $model->get()->where($criteria)->all();
    }
    
    public function create(array $data): Model {
        $model = new $this->modelClass;
        $model->fill($data);
        $model->create();
        return $model;
    }
}
```

**App/Exceptions/AppException.php**
```php
<?php
namespace App\Exceptions;

use Exception;

abstract class AppException extends Exception {
    protected array $context = [];
    
    public function __construct(string $message, array $context = [], int $code = 0) {
        parent::__construct($message, $code);
        $this->context = $context;
    }
    
    public function getContext(): array {
        return $this->context;
    }
}
```

#### 1.3. Validation Base
**App/Validators/BaseValidator.php**
```php
<?php
namespace App\Validators;

class ValidationResult {
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = []
    ) {}
    
    public static function success(): self {
        return new self(true);
    }
    
    public static function failed(array $errors): self {
        return new self(false, $errors);
    }
}

abstract class BaseValidator {
    abstract public function validate(array $data): ValidationResult;
    
    protected function isValidTimeFormat(string $time): bool {
        return (bool) preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
}
```

**Deliverable:**
- [ ] Klasör yapısı oluşturuldu
- [ ] Base sınıflar yazıldı
- [ ] Namespace autoloading doğrulandı

---

### Faz 2: İlk Service Örneği - ScheduleService (2 Hafta)
**Amaç:** En kritik modül olan Schedule Service'i oluştur, pattern'i oturtur.

#### 2.1. DTOs Oluştur
**App/DTOs/ScheduleItemData.php**
```php
<?php
namespace App\DTOs;

readonly class ScheduleItemData {
    public function __construct(
        public int $scheduleId,
        public int $dayIndex,
        public int $weekIndex,
        public string $startTime,
        public string $endTime,
        public string $status,
        public ?array $data = null,
        public ?array $detail = null
    ) {}
    
    public static function fromArray(array $data): self {
        return new self(
            scheduleId: $data['schedule_id'],
            dayIndex: $data['day_index'],
            weekIndex: $data['week_index'] ?? 0,
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            status: $data['status'],
            data: $data['data'] ?? null,
            detail: $data['detail'] ?? null
        );
    }
}
```

#### 2.2. Validator Oluştur
**App/Validators/ScheduleItemValidator.php**
```php
<?php
namespace App\Validators;

class ScheduleItemValidator extends BaseValidator {
    public function validate(array $data): ValidationResult {
        $errors = [];
        
        // Zaman validasyonu
        if (!isset($data['start_time']) || !$this->isValidTimeFormat($data['start_time'])) {
            $errors[] = 'Geçersiz başlangıç saati formatı';
        }
        
        if (!isset($data['end_time']) || !$this->isValidTimeFormat($data['end_time'])) {
            $errors[] = 'Geçersiz bitiş saati formatı';
        }
        
        if (isset($data['start_time'], $data['end_time']) && $data['start_time'] >= $data['end_time']) {
            $errors[] = 'Başlangıç saati bitiş saatinden küçük olmalı';
        }
        
        // Gün validasyonu
        if (!isset($data['day_index']) || $data['day_index'] < 0 || $data['day_index'] > 6) {
            $errors[] = 'Geçersiz gün indeksi';
        }
        
        // Status validasyonu
        $validStatuses = ['single', 'group', 'preferred', 'unavailable'];
        if (!isset($data['status']) || !in_array($data['status'], $validStatuses)) {
            $errors[] = 'Geçersiz status değeri';
        }
        
        return empty($errors) ? ValidationResult::success() : ValidationResult::failed($errors);
    }
}
```

#### 2.3. Repository Oluştur
**App/Repositories/ScheduleRepository.php**
```php
<?php
namespace App\Repositories;

use App\Models\Schedule;
use App\Models\ScheduleItem;

class ScheduleRepository extends BaseRepository {
    protected string $modelClass = Schedule::class;
    
    public function findOrCreate(array $criteria): Schedule {
        $schedule = new Schedule();
        return $schedule->firstOrCreate($criteria);
    }
    
    public function findConflictingItems(
        int $scheduleId, 
        int $dayIndex, 
        int $weekIndex, 
        string $startTime, 
        string $endTime
    ): array {
        return (new ScheduleItem())->get()
            ->where([
                'schedule_id' => $scheduleId,
                'day_index' => $dayIndex,
                'week_index' => $weekIndex
            ])
            ->all();
    }
    
    // Batch query optimization
    public function findMultipleByOwners(array $ownerCriteria): array {
        // Optimized batch query implementation
        $query = "SELECT * FROM schedules WHERE ";
        // ... complex batch query
    }
}
```

#### 2.4. Service Oluştur (İlk Versiyon - Basit)
**App/Services/ScheduleService.php**
```php
<?php
namespace App\Services;

use App\Repositories\ScheduleRepository;
use App\Validators\ScheduleItemValidator;
use App\DTOs\ScheduleItemData;
use App\Exceptions\ValidationException;

class ScheduleService extends BaseService {
    private ScheduleRepository $scheduleRepo;
    private ScheduleItemValidator $validator;
    
    public function __construct() {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->validator = new ScheduleItemValidator();
    }
    
    public function saveScheduleItems(array $itemsData): array {
        // Önce tüm itemları validate et
        foreach ($itemsData as $itemData) {
            $result = $this->validator->validate($itemData);
            if (!$result->isValid) {
                throw new ValidationException(
                    'Validation failed: ' . implode(', ', $result->errors)
                );
            }
        }
        
        // Transaction başlat
        $this->beginTransaction();
        
        try {
            $createdIds = [];
            
            foreach ($itemsData as $itemData) {
                $dto = ScheduleItemData::fromArray($itemData);
                // İş mantığı burada...
            }
            
            $this->commit();
            return $createdIds;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}
```

#### 2.5. Controller'ı Güncelle (Facade Pattern)
**App/Controllers/ScheduleController.php**
```php
// Mevcut saveScheduleItems metodunu güncelle
public function saveScheduleItems(array $itemsData): array {
    // Feature flag ile yeni/eski sistemi toggle edebilme
    if ($this->useNewService()) {
        $service = new ScheduleService();
        return $service->saveScheduleItems($itemsData);
    } else {
        // Eski kod burada kalır (backward compatibility)
        return $this->oldSaveScheduleItems($itemsData);
    }
}

private function useNewService(): bool {
    // Setting'den veya environment variable'dan kontrol
    return getSettingValue('use_new_schedule_service') === '1';
}

// Eski kodu rename et, silme
private function oldSaveScheduleItems(array $itemsData): array {
    // Mevcut kod buraya taşınır
}
```

**Deliverable:**
- [ ] ScheduleItemData DTO oluşturuldu
- [ ] ScheduleItemValidator yazıldı ve test edildi
- [ ] ScheduleRepository oluşturuldu
- [ ] ScheduleService ilk versiyonu yazıldı
- [ ] Controller'da feature flag ile entegre edildi
- [ ] Parallel testing (eski ve yeni sistem karşılaştırması)

---

### Faz 3: Service Layer'ı Genişlet (2 Hafta)
**Amaç:** ScheduleService'i tam fonksiyonel hale getir, diğer servisler için template oluştur.

#### 3.1. ScheduleService'i Tamamla
```php
class ScheduleService extends BaseService {
    private ConflictResolver $conflictResolver;
    private TimelineManager $timelineManager;
    
    // Tüm metotlar service'e taşınır:
    // - saveScheduleItems
    // - deleteScheduleItems
    // - checkConflicts
    // - etc.
}
```

#### 3.2. Helper Services Oluştur
**App/Services/ConflictResolver.php**
```php
class ConflictResolver {
    public function resolve(ScheduleItem $existing, ScheduleItemData $new): ConflictResult {
        // Sadece çakışma kontrolü mantığı
    }
}
```

**App/Services/TimelineManager.php**
```php
class TimelineManager {
    public function flattenTimeline(array $items, string $newStart, string $newEnd): array {
        // Timeline flattening algoritması
    }
}
```

#### 3.3. Diğer Servisler Oluştur
- **LessonService**: Ders CRUD ve iş kuralları
- **UserService**: Kullanıcı yönetimi
- **ClassroomService**: Derslik yönetimi

**Deliverable:**
- [ ] ScheduleService %100 fonksiyonel
- [ ] ConflictResolver ayrıldı
- [ ] TimelineManager ayrıldı
- [ ] LessonService, UserService, ClassroomService oluşturuldu
- [ ] Tüm controller'lar service kullanıyor (feature flag ile)

---

### Faz 4: Repository Pattern Tamamlama (1 Hafta)
**Amaç:** Tüm database query'leri repository'lere taşı.

#### 4.1. Repository'leri Genişlet
```php
class LessonRepository extends BaseRepository {
    protected string $modelClass = Lesson::class;
    
    public function findWithChildren(int $lessonId): ?Lesson {
        return (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
    }
    
    public function findByProgram(int $programId, int $semesterNo): array {
        return (new Lesson())->get()->where([
            'program_id' => $programId,
            'semester_no' => $semesterNo
        ])->all();
    }
}
```

#### 4.2. Service'lerde Kullan
```php
class LessonService extends BaseService {
    private LessonRepository $lessonRepo;
    
    public function getLessonWithChildren(int $lessonId): ?Lesson {
        return $this->lessonRepo->findWithChildren($lessonId);
    }
}
```

**Deliverable:**
- [ ] Tüm model'ler için repository oluşturuldu
- [ ] Service'ler repository kullanıyor
- [ ] Direct model query'leri elimine edildi

---

### Faz 5: Model Refactoring (1 Hafta)
**Amaç:** Model'leri sadeleştir, Active Record'dan Data Model'e geçiş.

#### 5.1. Model'den İş Mantığını Temizle
```php
// ÖNCE (Active Record)
class Lesson extends Model {
    public function IsScheduleComplete(string $type): bool {
        // Karmaşık hesaplama mantığı
    }
}

// SONRA (Data Model)
class Lesson extends Model {
    // Sadece data properties ve relationships
    // İş mantığı LessonService'de
}
```

#### 5.2. Business Logic Service'e Taşı
```php
class LessonService extends BaseService {
    public function isScheduleComplete(Lesson $lesson, string $type): bool {
        // Mantık buraya taşındı
    }
}
```

**Deliverable:**
- [ ] Model'lerden iş mantığı kaldırıldı
- [ ] Model'ler sadece data container
- [ ] Tüm iş mantığı service'lerde

---

### Faz 6: Type Safety ve Code Quality (1 Hafta)
**Amaç:** Kod kalitesini artır, type safety ekle.

#### 6.1. Enum'lar Ekle
```php
enum ScheduleItemStatus: string {
    case SINGLE = 'single';
    case GROUP = 'group';
    case PREFERRED = 'preferred';
    case UNAVAILABLE = 'unavailable';
    
    public function isDummy(): bool {
        return in_array($this, [self::PREFERRED, self::UNAVAILABLE]);
    }
}
```

#### 6.2. Custom Exception'lar
```php
class ScheduleConflictException extends AppException {
    public function __construct(
        public readonly ScheduleItem $conflictingItem,
        string $message
    ) {
        parent::__construct($message);
    }
}
```

#### 6.3. Type Hints Ekle
```php
public function saveScheduleItems(array $itemsData): array {
    // Önce
}

public function saveScheduleItems(array $itemsData): SaveScheduleResult {
    // Sonra - type-safe return
}
```

**Deliverable:**
- [ ] Tüm enum'lar oluşturuldu
- [ ] Custom exception'lar eklendi
- [ ] Type hints tamamlandı
- [ ] PHPStan/Psalm ile static analysis

---

## Feature Flag Stratejisi

Her faz için:

```php
// App/Core/FeatureFlags.php
class FeatureFlags {
    public static function useNewScheduleService(): bool {
        return self::isEnabled('new_schedule_service');
    }
    
    public static function useNewLessonService(): bool {
        return self::isEnabled('new_lesson_service');
    }
    
    private static function isEnabled(string $flag): bool {
        // Database setting'den oku
        return getSettingValue($flag) === '1';
    }
}

// Controller'da kullanım
if (FeatureFlags::useNewScheduleService()) {
    $service = new ScheduleService();
    return $service->saveScheduleItems($itemsData);
} else {
    return $this->oldSaveScheduleItems($itemsData);
}
```

---

## Testing Stratejisi

### Faz 2'den İtibaren Her Service İçin:

#### 1. Unit Tests
```php
class ScheduleServiceTest extends TestCase {
    public function test_save_schedule_items_validates_data() {
        $service = new ScheduleService();
        
        $this->expectException(ValidationException::class);
        
        $service->saveScheduleItems([
            ['start_time' => '10:00', 'end_time' => '09:00'] // Invalid
        ]);
    }
}
```

#### 2. Integration Tests
```php
class ScheduleServiceIntegrationTest extends TestCase {
    public function test_save_schedule_items_creates_records() {
        $service = new ScheduleService();
        
        $result = $service->saveScheduleItems([
            // Valid data
        ]);
        
        $this->assertDatabaseHas('schedule_items', ['id' => $result[0]]);
    }
}
```

#### 3. Parallel Testing (Eski vs Yeni)
```php
class ParallelComparisonTest extends TestCase {
    public function test_old_and_new_service_produce_same_result() {
        $oldController = new ScheduleController();
        $newService = new ScheduleService();
        
        $testData = [...];
        
        $oldResult = $oldController->oldSaveScheduleItems($testData);
        $newResult = $newService->saveScheduleItems($testData);
        
        $this->assertEquals($oldResult, $newResult);
    }
}
```

---

## Rollback Planı

Her faz için rollback mekanizması:

### 1. Code Level
```php
// Feature flag kapatılır
UPDATE settings SET value = '0' WHERE name = 'new_schedule_service';
```

### 2. Database Level
```sql
-- Migration rollback scripts hazır
-- down() metotları test edilmiş
```

### 3. Monitoring
```php
// Her service call loglanır
$this->logger->info('Service called', [
    'service' => 'ScheduleService',
    'method' => 'saveScheduleItems',
    'duration' => $duration,
    'success' => $success
]);

// Hata oranı izlenir
if (error_rate > threshold) {
    // Auto-rollback veya alert
}
```

---

## Risk Yönetimi

### Yüksek Riskli Noktalar

1. **Schedule Service Migration**
   - Risk: En karmaşık modül
   - Mitigation: Çok detaylı test, parallel running

2. **Transaction Yönetimi**
   - Risk: Nested transaction sorunları
   - Mitigation: Transaction helper class, dikkatli test

3. **Performance Regression**
   - Risk: Yeni katmanlar overhead ekler
   - Mitigation: Benchmark testleri, profiling

### Risk Azaltma Stratejileri

- **Incremental Migration**: Modül modül geçiş
- **Feature Flags**: Anında geri dönüş imkanı
- **Parallel Running**: Eski ve yeni sistem karşılaştırması
- **Automated Testing**: Her değişiklik test edilir
- **Code Review**: Her PR detaylı review
- **Monitoring**: Performans ve hata tracking

---

## Zaman Çizelgesi

| Faz | Süre | Başlangıç | Bitiş | Deliverables |
|-----|------|-----------|-------|--------------|
| **Faz 1** | 1 hafta | Hafta 1 | Hafta 1 | Altyapı |
| **Faz 2** | 2 hafta | Hafta 2 | Hafta 3 | ScheduleService |
| **Faz 3** | 2 hafta | Hafta 4 | Hafta 5 | Tüm Service'ler |
| **Faz 4** | 1 hafta | Hafta 6 | Hafta 6 | Repository Pattern |
| **Faz 5** | 1 hafta | Hafta 7 | Hafta 7 | Model Refactoring |
| **Faz 6** | 1 hafta | Hafta 8 | Hafta 8 | Type Safety |
| **TOPLAM** | **8 hafta** | - | - | - |

---

## Success Metrics

Migration başarısı şu metriklerle ölçülür:

### Kod Kalitesi
- [ ] %80+ test coverage
- [ ] 0 lint hatası
- [ ] PHPStan level 6+ pass

### Performans
- [ ] Response time <= mevcut sistem
- [ ] Database query count <= mevcut sistem
- [ ] Memory usage <= mevcut sistem + 10%

### Stabilite
- [ ] 0 production bug (ilk 2 hafta)
- [ ] Error rate < %0.1
- [ ] Uptime > %99.9

### Developer Experience
- [ ] Yeni özellik ekleme süresi 50% azalma
- [ ] Bug fix süresi 40% azalma
- [ ] Code review süresi 30% azalma

---

## İlk Adım: Faz 1 Detaylı Plan

### Hafta 1 - Gün Gün Plan

#### Gün 1-2: Infrastructure
- [ ] Klasör yapısı oluştur
- [ ] BaseService yazılacak ve test edilecek
- [ ] BaseRepository yazılacak ve test edilecek
- [ ] Namespace autoloading doğrulanacak

#### Gün 3: Validation Infrastructure
- [ ] ValidationResult class
- [ ] BaseValidator class
- [ ] İlk validator örneği (basit)
- [ ] Unit testler

#### Gün 4: Exception Infrastructure
- [ ] AppException base class
- [ ] İlk custom exception örnekleri
- [ ] Exception handling pattern belirleme

#### Gün 5: Dokümantasyon ve Review
- [ ] Mimari dokümantasyonu güncelle
- [ ] Code review
- [ ] Team training/walkthrough
- [ ] İleriki fazlar için detay planlama

---

## Sonuç

Bu plan:

✅ **Incremental**: Her faz bağımsız değer üretir  
✅ **Safe**: Feature flag ile risk minimize edilmiş  
✅ **Testable**: Her adım test edilebilir  
✅ **Reversible**: Rollback planı hazır  
✅ **Pragmatic**: Mevcut kod korunmuş, kademeli geçiş  

**İlk adım:** Faz 1'i uygula (1 hafta), pattern'i oturttuktan sonra diğer fazlara geç.
