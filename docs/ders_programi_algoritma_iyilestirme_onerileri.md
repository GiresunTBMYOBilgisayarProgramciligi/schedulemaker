# Ders Programı Algoritması İyileştirme Önerileri

Bu dokümantasyon, mevcut ders programı oluşturma algoritmasının performans, verimlilik ve stabilite açısından iyileştirilebilecek alanlarını ve somut önerileri içermektedir.

## İyileştirme Kategorileri

### 1. Performans İyileştirmeleri

#### 1.1. Veritabanı Sorgu Optimizasyonu

**Mevcut Durum:**
```php
// Her owner için ayrı ayrı sorgu
foreach ($owners as $owner) {
    $schedule = (new Schedule())->firstOrCreate([...]);
    $existingItems = (new ScheduleItem())->get()->where([...])->all();
}
```

**Sorun:**
- N+1 sorgu problemi
- Her paydaş için ayrı veritabanı sorgusu
- Büyük veri setlerinde yavaşlama

**Öneri:**
```php
// Batch sorgu - tüm owner'ları tek sorguda al
$ownerIds = array_column(array_filter($owners, fn($o) => $o['type'] === 'user'), 'id');
$schedules = (new Schedule())->get()
    ->where([
        'owner_type' => 'user',
        'owner_id' => ['in' => $ownerIds],
        'semester' => $semester,
        'academic_year' => $academicYear,
        'type' => $type
    ])
    ->all();

// Schedule'ları lookup dictionary'ye dönüştür
$scheduleLookup = [];
foreach ($schedules as $schedule) {
    $key = "{$schedule->owner_type}_{$schedule->owner_id}";
    $scheduleLookup[$key] = $schedule;
}
```

**Beklenen Kazanç:** 
- 10+ paydaş için %70-80 performans artışı
- Veritabanı yükünde dramatik azalma

---

#### 1.2. Önbellekleme (Caching) Stratejisi

**Öneri - Lesson Cache:**
```php
class ScheduleController {
    private array $lessonCache = [];
    
    private function getLessonWithCache(int $lessonId): ?Lesson {
        if (!isset($this->lessonCache[$lessonId])) {
            $this->lessonCache[$lessonId] = (new Lesson())
                ->where(['id' => $lessonId])
                ->with(['childLessons'])
                ->first();
        }
        return $this->lessonCache[$lessonId];
    }
}
```

**Öneri - Schedule Cache:**
```php
private array $scheduleCache = [];

private function getScheduleWithCache(array $criteria): Schedule {
    $key = md5(json_encode($criteria));
    
    if (!isset($this->scheduleCache[$key])) {
        $this->scheduleCache[$key] = (new Schedule())->firstOrCreate($criteria);
    }
    
    return $this->scheduleCache[$key];
}
```

**Beklenen Kazanç:**
- Aynı ders/schedule için tekrarlanan sorgular elimine edilir
- Batch işlemlerde %40-50 hız artışı

---

#### 1.3. Timeline Flattening Optimizasyonu

**Mevcut Durum:**
```php
// O(n²) karmaşıklık - her aralık için her item kontrol edilir
for ($i = 0; $i < count($points) - 1; $i++) {
    foreach ($involvedItems as $item) {
        if ($item->getShortStartTime() <= $pStart && $item->getShortEndTime() >= $pEnd) {
            // merge
        }
    }
}
```

**Öneri - Event-Based Approach:**
```php
private function optimizedTimelineFlattening(array $involvedItems, string $newStart, string $newEnd, array $newData): array {
    // Events: +1 for start, -1 for end
    $events = [];
    
    // Yeni item events
    $events[] = ['time' => $newStart, 'type' => 'start', 'data' => $newData];
    $events[] = ['time' => $newEnd, 'type' => 'end', 'data' => $newData];
    
    // Mevcut item events
    foreach ($involvedItems as $item) {
        $events[] = ['time' => $item->getShortStartTime(), 'type' => 'start', 'data' => $item->data];
        $events[] = ['time' => $item->getShortEndTime(), 'type' => 'end', 'data' => $item->data];
    }
    
    // Zamanına göre sırala
    usort($events, fn($a, $b) => strcmp($a['time'], $b['time']) ?: ($a['type'] === 'start' ? -1 : 1));
    
    // Sweep line algoritması
    $activeData = [];
    $intervals = [];
    $lastTime = null;
    
    foreach ($events as $event) {
        if ($lastTime !== null && !empty($activeData) && $lastTime < $event['time']) {
            $intervals[] = [
                'start' => $lastTime,
                'end' => $event['time'],
                'data' => array_values($activeData)
            ];
        }
        
        if ($event['type'] === 'start') {
            foreach ($event['data'] as $d) {
                $activeData[$d['lesson_id']] = $d;
            }
        } else {
            foreach ($event['data'] as $d) {
                unset($activeData[$d['lesson_id']]);
            }
        }
        
        $lastTime = $event['time'];
    }
    
    return $intervals;
}
```

**Beklenen Kazanç:**
- O(n²) → O(n log n) karmaşıklık azalması
- Çok sayıda overlap durumunda %60-70 performans artışı

---

### 2. Mimari İyileştirmeler

#### 2.1. Service Layer Ayrımı

**Sorun:**
- Controller çok fazla sorumluluk taşıyor
- İş mantığı controller'da
- Test edilebilirlik düşük

**Öneri:**
```php
// App/Services/ScheduleService.php
class ScheduleService {
    private ConflictResolver $conflictResolver;
    private TimelineManager $timelineManager;
    private ScheduleRepository $scheduleRepo;
    
    public function __construct() {
        $this->conflictResolver = new ConflictResolver();
        $this->timelineManager = new TimelineManager();
        $this->scheduleRepo = new ScheduleRepository();
    }
    
    public function saveScheduleItems(array $itemsData): array {
        // İş mantığı burada
    }
}

// App/Services/ConflictResolver.php
class ConflictResolver {
    public function checkConflict(ScheduleItem $existing, array $newData, Lesson $lesson): ?string {
        // Sadece çakışma kontrolü mantığı
    }
    
    public function resolvePreferred(ScheduleItem $preferred, string $newStart, string $newEnd): void {
        // Sadece preferred çözümleme
    }
}

// App/Services/TimelineManager.php
class TimelineManager {
    public function flattenTimeline(array $items, string $newStart, string $newEnd, array $newData): array {
        // Timeline flattening mantığı
    }
}

// App/Repositories/ScheduleRepository.php
class ScheduleRepository {
    public function findOrCreateSchedules(array $criteria): array {
        // Batch schedule bulma/oluşturma
    }
    
    public function findConflictingItems(Schedule $schedule, int $dayIndex, int $weekIndex, string $start, string $end): array {
        // Optimized sorgu
    }
}
```

**Kazanç:**
- Tek sorumluluk prensibi (SRP)
- Test edilebilir kod
- Yeniden kullanılabilir servisler
- Bakım kolaylığı

---

#### 2.2. Event-Driven Architecture

**Öneri:**
```php
// App/Events/ScheduleItemCreated.php
class ScheduleItemCreated {
    public function __construct(
        public ScheduleItem $item,
        public array $affectedOwners
    ) {}
}

// App/Listeners/SyncChildLessons.php
class SyncChildLessons {
    public function handle(ScheduleItemCreated $event): void {
        // Child lesson senkronizasyonu
    }
}

// App/Listeners/UpdateLessonProgress.php
class UpdateLessonProgress {
    public function handle(ScheduleItemCreated $event): void {
        // IsScheduleComplete hesaplama
    }
}

// App/Listeners/LogScheduleChange.php
class LogScheduleChange {
    public function handle(ScheduleItemCreated $event): void {
        // Loglama
    }
}

// Kullanım
EventDispatcher::dispatch(new ScheduleItemCreated($newItem, $owners));
```

**Kazanç:**
- Loosely coupled kod
- Yan etkilerin merkezi yönetimi
- Yeni özellik ekleme kolaylığı
- Async işlem potansiyeli

---

### 3. Hata Yönetimi İyileştirmeleri

#### 3.1. Custom Exception Sınıfları

**Öneri:**
```php
// App/Exceptions/ScheduleException.php
abstract class ScheduleException extends Exception {
    protected array $context = [];
    
    public function __construct(string $message, array $context = []) {
        parent::__construct($message);
        $this->context = $context;
    }
    
    public function getContext(): array {
        return $this->context;
    }
}

// App/Exceptions/ScheduleConflictException.php
class ScheduleConflictException extends ScheduleException {
    public function __construct(
        public readonly ScheduleItem $conflictingItem,
        public readonly Schedule $schedule,
        string $message,
        array $context = []
    ) {
        parent::__construct($message, $context);
    }
}

// App/Exceptions/LessonHourExceededException.php
class LessonHourExceededException extends ScheduleException {
    public function __construct(
        public readonly Lesson $lesson,
        public readonly int $remainingSize,
        array $context = []
    ) {
        $message = "Ders saati aşıldı: {$lesson->name}, Kalan: {$remainingSize}";
        parent::__construct($message, $context);
    }
}

// Kullanım
if ($error) {
    throw new ScheduleConflictException(
        conflictingItem: $existingItem,
        schedule: $currentSchedule,
        message: $error,
        context: ['lesson' => $lesson, 'day_index' => $dayIndex]
    );
}
```

**Kazanç:**
- Tip güvenli exception handling
- Detaylı hata konteksti
- Frontend'e yapılandırılmış hata bilgisi
- Hata loglama ve monitoring kolaylığı

---

#### 3.2. Validation Layer

**Öneri:**
```php
// App/Validators/ScheduleItemValidator.php
class ScheduleItemValidator {
    public function validate(array $itemData): ValidationResult {
        $errors = [];
        
        // Zaman validasyonu
        if (!$this->isValidTimeFormat($itemData['start_time'])) {
            $errors[] = new ValidationError('start_time', 'Geçersiz saat formatı');
        }
        
        if ($itemData['start_time'] >= $itemData['end_time']) {
            $errors[] = new ValidationError('time_range', 'Başlangıç saati bitiş saatinden küçük olmalı');
        }
        
        // Gün/Hafta validasyonu
        if ($itemData['day_index'] < 0 || $itemData['day_index'] > 6) {
            $errors[] = new ValidationError('day_index', 'Geçersiz gün indeksi');
        }
        
        // Status validasyonu
        if (!in_array($itemData['status'], ['single', 'group', 'preferred', 'unavailable'])) {
            $errors[] = new ValidationError('status', 'Geçersiz status değeri');
        }
        
        return empty($errors) 
            ? ValidationResult::success() 
            : ValidationResult::failed($errors);
    }
    
    private function isValidTimeFormat(string $time): bool {
        return (bool) preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
}

// App/ValueObjects/ValidationResult.php
class ValidationResult {
    private function __construct(
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
```

**Kazanç:**
- Erken hata tespiti
- Transaction başlamadan önce validasyon
- Veritabanı yükünün azalması
- Daha anlamlı hata mesajları

---

### 4. Veri Tutarlılığı İyileştirmeleri

#### 4.1. Database Constraints

**Öneri:**
```sql
-- Zaman tutarlılığı constraint
ALTER TABLE schedule_items 
ADD CONSTRAINT check_time_order 
CHECK (start_time < end_time);

-- Gün indeksi constraint
ALTER TABLE schedule_items 
ADD CONSTRAINT check_day_index 
CHECK (day_index BETWEEN 0 AND 6);

-- Hafta indeksi constraint
ALTER TABLE schedule_items 
ADD CONSTRAINT check_week_index 
CHECK (week_index >= 0);

-- Unique constraint - aynı schedule'da aynı saatte single item tekrarı önleme
CREATE UNIQUE INDEX idx_single_item_unique 
ON schedule_items (schedule_id, day_index, week_index, start_time, end_time)
WHERE status = 'single';
```

**Kazanç:**
- Veritabanı seviyesinde veri tutarlılığı
- Application bug'larına karşı koruma
- Concurrent işlemlerde tutarlılık

---

#### 4.2. Optimistic Locking

**Öneri:**
```php
// Schedule model'e version field ekle
class Schedule extends Model {
    protected array $fillable = [..., 'version'];
    
    public function update(?array $data = null): bool {
        // Version kontrolü
        $currentVersion = $this->version;
        $this->version = $currentVersion + 1;
        
        $affected = $this->database->query()
            ->update($this->table)
            ->set(array_merge($data ?? $this->data, ['version' => $this->version]))
            ->where('id', $this->id)
            ->where('version', $currentVersion)
            ->execute();
            
        if ($affected === 0) {
            throw new OptimisticLockException("Kayıt başka bir işlem tarafından güncellenmiş");
        }
        
        return true;
    }
}
```

**Kazanç:**
- Concurrent update sorunlarını önler
- Lost update problemi çözülür
- Multi-user environment'ta güvenlik

---

### 5. Ölçeklenebilirlik İyileştirmeleri

#### 5.1. Queue System - Async Processing

**Öneri:**
```php
// App/Jobs/ProcessScheduleItems.php
class ProcessScheduleItems {
    public function __construct(
        private array $itemsData,
        private string $userId
    ) {}
    
    public function handle(): void {
        $service = new ScheduleService();
        $result = $service->saveScheduleItems($this->itemsData);
        
        // WebSocket ile frontend'e bildirim
        WebSocketServer::send($this->userId, [
            'type' => 'schedule_processed',
            'result' => $result
        ]);
    }
}

// Controller'da
public function saveScheduleItemsAction(): void {
    $itemsData = json_decode($this->request->body, true);
    
    if (count($itemsData) > 10) {
        // Büyük batch'ler için async
        Queue::push(new ProcessScheduleItems($itemsData, $this->auth->user->id));
        
        $this->response->json([
            'success' => true,
            'message' => 'İşlem arka planda devam ediyor',
            'async' => true
        ]);
    } else {
        // Küçük batch'ler için sync
        $result = $this->scheduleService->saveScheduleItems($itemsData);
        $this->response->json(['success' => true, 'data' => $result]);
    }
}
```

**Kazanç:**
- Büyük batch işlemlerde timeout önleme
- Kullanıcı deneyimi iyileştirme
- Server kaynaklarının daha iyi kullanımı

---

#### 5.2. Database Indexing

**Öneri:**
```sql
-- Sık sorgulanan alanlar için composite index
CREATE INDEX idx_schedule_items_lookup 
ON schedule_items (schedule_id, day_index, week_index, start_time, end_time);

-- Status bazlı sorgular için
CREATE INDEX idx_schedule_items_status 
ON schedule_items (schedule_id, status, day_index);

-- Owner lookup için
CREATE INDEX idx_schedules_owner 
ON schedules (owner_type, owner_id, semester, academic_year, type);

-- Partial index - sadece group items için
CREATE INDEX idx_group_items 
ON schedule_items (schedule_id, day_index, week_index) 
WHERE status = 'group';
```

**Kazanç:**
- Sorgu hızında 5-10x iyileşme
- Çakışma kontrolü optimizasyonu
- Büyük veri setlerinde performans

---

### 6. Monitoring ve Debugging

#### 6.1. Performance Monitoring

**Öneri:**
```php
// App/Monitoring/PerformanceMonitor.php
class PerformanceMonitor {
    private array $metrics = [];
    
    public function startTimer(string $section): void {
        $this->metrics[$section] = ['start' => microtime(true)];
    }
    
    public function endTimer(string $section): void {
        if (isset($this->metrics[$section])) {
            $this->metrics[$section]['duration'] = microtime(true) - $this->metrics[$section]['start'];
        }
    }
    
    public function recordQuery(string $query, float $duration): void {
        $this->metrics['queries'][] = [
            'query' => $query,
            'duration' => $duration
        ];
    }
    
    public function getReport(): array {
        return [
            'sections' => $this->metrics,
            'total_time' => array_sum(array_column($this->metrics, 'duration')),
            'query_count' => count($this->metrics['queries'] ?? []),
            'slow_queries' => array_filter($this->metrics['queries'] ?? [], fn($q) => $q['duration'] > 0.1)
        ];
    }
}

// Kullanım
$monitor = new PerformanceMonitor();
$monitor->startTimer('conflict_check');
// ... çakışma kontrolü
$monitor->endTimer('conflict_check');

$this->logger->info('Performance Report', $monitor->getReport());
```

**Kazanç:**
- Performans darboğazlarının tespiti
- Production'da yavaş işlemlerin belirlenmesi
- Optimizasyon kararlarında veri tabanlı yaklaşım

---

#### 6.2. Debug Mode

**Öneri:**
```php
class ScheduleService {
    private bool $debugMode = false;
    private array $debugLog = [];
    
    public function enableDebug(): void {
        $this->debugMode = true;
    }
    
    private function debug(string $message, array $context = []): void {
        if ($this->debugMode) {
            $this->debugLog[] = [
                'timestamp' => microtime(true),
                'message' => $message,
                'context' => $context,
                'memory' => memory_get_usage(true)
            ];
        }
    }
    
    public function getDebugLog(): array {
        return $this->debugLog;
    }
    
    public function saveScheduleItems(array $itemsData): array {
        $this->debug('Starting saveScheduleItems', ['item_count' => count($itemsData)]);
        
        foreach ($itemsData as $itemData) {
            $this->debug('Processing item', ['item' => $itemData]);
            // ...
        }
        
        $this->debug('Completed saveScheduleItems', ['created_count' => count($createdIds)]);
        
        return $createdIds;
    }
}
```

**Kazanç:**
- Production sorunlarını debug etme kolaylığı
- Detaylı execution trace
- Memory leak tespiti

---

### 7. Kod Kalitesi İyileştirmeleri

#### 7.1. Type Safety

**Öneri:**
```php
// App/DTOs/ScheduleItemData.php
readonly class ScheduleItemData {
    public function __construct(
        public int $scheduleId,
        public int $dayIndex,
        public int $weekIndex,
        public string $startTime,
        public string $endTime,
        public ScheduleItemStatus $status,
        public ?LessonData $lessonData = null,
        public array $detail = []
    ) {
        // Validasyon constructor'da
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Start time must be before end time');
        }
    }
    
    public static function fromArray(array $data): self {
        return new self(
            scheduleId: $data['schedule_id'],
            dayIndex: $data['day_index'],
            weekIndex: $data['week_index'] ?? 0,
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            status: ScheduleItemStatus::from($data['status']),
            lessonData: isset($data['data']) ? LessonData::fromArray($data['data']) : null,
            detail: $data['detail'] ?? []
        );
    }
}

// App/Enums/ScheduleItemStatus.php
enum ScheduleItemStatus: string {
    case SINGLE = 'single';
    case GROUP = 'group';
    case PREFERRED = 'preferred';
    case UNAVAILABLE = 'unavailable';
    
    public function isDummy(): bool {
        return in_array($this, [self::PREFERRED, self::UNAVAILABLE]);
    }
}

// Kullanım
public function saveScheduleItems(array $itemsData): array {
    $items = array_map(fn($data) => ScheduleItemData::fromArray($data), $itemsData);
    
    foreach ($items as $item) {
        if ($item->status->isDummy()) {
            // ...
        }
    }
}
```

**Kazanç:**
- Compile-time hata tespiti
- IDE autocomplete desteği
- Daha az runtime hatası
- Kod okunabilirliği

---

#### 7.2. Strategy Pattern - Conflict Resolution

**Öneri:**
```php
// App/Strategies/ConflictResolution/ConflictStrategy.php
interface ConflictResolutionStrategy {
    public function canHandle(ScheduleItemStatus $status): bool;
    public function resolve(ScheduleItem $existing, ScheduleItemData $new, Lesson $lesson): ConflictResult;
}

// App/Strategies/ConflictResolution/UnavailableConflictStrategy.php
class UnavailableConflictStrategy implements ConflictResolutionStrategy {
    public function canHandle(ScheduleItemStatus $status): bool {
        return $status === ScheduleItemStatus::UNAVAILABLE;
    }
    
    public function resolve(ScheduleItem $existing, ScheduleItemData $new, Lesson $lesson): ConflictResult {
        return ConflictResult::error("Bu saat aralığı uygun değil");
    }
}

// App/Strategies/ConflictResolution/GroupConflictStrategy.php
class GroupConflictStrategy implements ConflictResolutionStrategy {
    public function canHandle(ScheduleItemStatus $status): bool {
        return $status === ScheduleItemStatus::GROUP;
    }
    
    public function resolve(ScheduleItem $existing, ScheduleItemData $new, Lesson $lesson): ConflictResult {
        if ($lesson->group_no < 1) {
            return ConflictResult::error("Grup dersi üzerine normal ders eklenemez");
        }
        
        foreach ($existing->getSlotDatas() as $slotData) {
            if ($slotData->lesson->id === $lesson->id) {
                return ConflictResult::error("Aynı ders aynı saatte tekrar eklenemez");
            }
            
            if ($slotData->lecturer->id === $new->lessonData->lecturerId) {
                return ConflictResult::error("Hoca aynı anda iki farklı derse giremez");
            }
            
            if ($slotData->lesson->group_no === $lesson->group_no) {
                return ConflictResult::error("Aynı grup numarasına sahip dersler çakışamaz");
            }
        }
        
        return ConflictResult::success();
    }
}

// App/Services/ConflictResolver.php
class ConflictResolver {
    private array $strategies = [];
    
    public function __construct() {
        $this->strategies = [
            new UnavailableConflictStrategy(),
            new SingleConflictStrategy(),
            new GroupConflictStrategy(),
            new PreferredConflictStrategy(),
        ];
    }
    
    public function resolve(ScheduleItem $existing, ScheduleItemData $new, Lesson $lesson): ConflictResult {
        foreach ($this->strategies as $strategy) {
            if ($strategy->canHandle($existing->status)) {
                return $strategy->resolve($existing, $new, $lesson);
            }
        }
        
        throw new LogicException("No strategy found for status: {$existing->status->value}");
    }
}
```

**Kazanç:**
- Open/Closed prensibi
- Yeni status türleri ekleme kolaylığı
- Test edilebilirlik
- Kod organizasyonu

---

## Öncelik Sıralaması

### Yüksek Öncelik (Hemen Uygulanmalı)

1. **Database Indexing** (Bölüm 5.2)
   - Kolay uygulanır, büyük performans kazancı
   - Risk: Düşük
   - Tahmini süre: 2 saat

2. **Validation Layer** (Bölüm 3.2)
   - Erken hata tespiti, veritabanı yükü azaltma
   - Risk: Düşük
   - Tahmini süre: 1 gün

3. **Veritabanı Sorgu Optimizasyonu** (Bölüm 1.1)
   - Önemli performans kazancı
   - Risk: Orta (dikkatli test gerekir)
   - Tahmini süre: 2-3 gün

4. **Custom Exception Sınıfları** (Bölüm 3.1)
   - Hata yönetimi iyileştirir
   - Risk: Düşük
   - Tahmini süre: 1 gün

### Orta Öncelik (Kısa-Orta Vadede)

5. **Önbellekleme Stratejisi** (Bölüm 1.2)
   - Performans kazancı
   - Risk: Orta
   - Tahmini süre: 2 gün

6. **Service Layer Ayrımı** (Bölüm 2.1)
   - Kod kalitesi, test edilebilirlik
   - Risk: Orta (büyük refactoring)
   - Tahmini süre: 1 hafta

7. **Performance Monitoring** (Bölüm 6.1)
   - Darboğaz tespiti
   - Risk: Düşük
   - Tahmini süre: 2 gün

8. **Database Constraints** (Bölüm 4.1)
   - Veri tutarlılığı
   - Risk: Orta (mevcut veri kontrolü gerekir)
   - Tahmini süre: 1 gün

### Düşük Öncelik (Uzun Vade)

9. **Timeline Flattening Opt.** (Bölüm 1.3)
   - Karmaşık algoritma değişikliği
   - Risk: Yüksek
   - Tahmini süre: 1 hafta

10. **Event-Driven Architecture** (Bölüm 2.2)
    - Büyük mimari değişiklik
    - Risk: Yüksek
    - Tahmini süre: 2 hafta

11. **Queue System** (Bölüm 5.1)
    - Infrastructure gereksinimi
    - Risk: Yüksek
    - Tahmini süre: 1 hafta

12. **Strategy Pattern** (Bölüm 7.2)
    - Kod kalitesi, büyük refactoring
    - Risk: Orta
    - Tahmini süre: 3-4 gün

---

## Hızlı Kazançlar (Quick Wins)

Minimum efor, maksimum etki:

### 1. Index Ekle (30 dakika)
```sql
CREATE INDEX idx_schedule_items_lookup ON schedule_items (schedule_id, day_index, week_index);
CREATE INDEX idx_schedules_owner ON schedules (owner_type, owner_id, semester, academic_year);
```

### 2. Lesson Cache Ekle (1 saat)
```php
private array $lessonCache = [];

private function getLessonWithCache(int $lessonId): ?Lesson {
    if (!isset($this->lessonCache[$lessonId])) {
        $this->lessonCache[$lessonId] = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
    }
    return $this->lessonCache[$lessonId];
}
```

### 3. Validation Ekle (2 saat)
```php
// saveScheduleItems başında
foreach ($itemsData as $itemData) {
    if ($itemData['start_time'] >= $itemData['end_time']) {
        throw new InvalidArgumentException('Başlangıç saati bitiş saatinden küçük olmalı');
    }
}
```

**Toplam: 3.5 saat, Tahmini kazanç: %30-40 performans artışı**

---

## Test Stratejisi

Her iyileştirme için:

### 1. Unit Tests
```php
class ConflictResolverTest extends TestCase {
    public function test_unavailable_conflict_returns_error() {
        $resolver = new ConflictResolver();
        $existing = new ScheduleItem(['status' => 'unavailable']);
        
        $result = $resolver->resolve($existing, $newData, $lesson);
        
        $this->assertTrue($result->hasError());
        $this->assertStringContains('uygun değil', $result->getError());
    }
}
```

### 2. Integration Tests
```php
class ScheduleServiceTest extends TestCase {
    public function test_concurrent_schedule_creation() {
        // Optimistic locking test
        // 2 paralel işlem aynı schedule'ı güncellemeye çalışır
    }
}
```

### 3. Performance Tests
```php
class SchedulePerformanceTest extends TestCase {
    public function test_save_100_items_under_2_seconds() {
        $items = $this->generateTestItems(100);
        
        $start = microtime(true);
        $service->saveScheduleItems($items);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(2.0, $duration);
    }
}
```

### 4. Load Tests
- Apache JMeter veya k6 ile
- 50 concurrent user
- 1000 request/saniye

---

## Rollback Planı

Her major değişiklik için:

1. **Feature Flag**
   ```php
   if (getSettingValue('use_new_timeline_algorithm') === '1') {
       return $this->optimizedTimelineFlattening(...);
   } else {
       return $this->originalTimelineFlattening(...);
   }
   ```

2. **Canary Deployment**
   - İlk %10 trafikte test
   - Sorun yoksa %100'e çık

3. **Database Migration Rollback Scripts**
   ```sql
   -- Migration up
   CREATE INDEX idx_...;
   
   -- Migration down
   DROP INDEX idx_...;
   ```

---

## Sonuç

Bu iyileştirmeler uygulandığında beklenen kazançlar:

- **Performans**: %60-80 hız artışı (özellikle büyük batch işlemlerinde)
- **Ölçeklenebilirlik**: 10x daha fazla concurrent user desteği
- **Stabilite**: %90+ azalma hata oranında
- **Bakım**: %50 azalma bug fix süresinde
- **Test Coverage**: %80+ kod coverage

**Önerilen İlk Adım:** Hızlı Kazançlar bölümünü uygula (3.5 saat), ardından Yüksek Öncelik itemlarına geç.
