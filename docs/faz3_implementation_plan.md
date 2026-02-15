# Faz 3 Implementation Planı: Service Layer Genişletme

## Genel Bakış

**Süre:** 2 Hafta  
**Durum:** Planning  
**Önkoşul:** Faz 2 tamamlandı (test ertelendi)

## Amaç

ScheduleService'i tam fonksiyonel hale getirmek ve diğer ana domain entity'ler için service'leri oluşturmak.

---

## Bölüm 1: ScheduleService Tamamlama

### 1.1. ConflictResolver (Helper Service)

**Dosya:** `App/Services/Helpers/ConflictResolver.php`

**Sorumluluklar:**
- Çakışma kontrolü mantığı
- Group item kuralları validation
- Preferred/Unavailable slot mantığı

**Mevcut Kod:**
- `ScheduleController::checkItemConflict()` - L1372-1466
- `ScheduleController::resolveConflict()` - L1506-1571

**Refactoring:**
```php
class ConflictResolver {
    public function checkConflict(
        ScheduleItemData $newItem,
        array $owners
    ): ?ConflictResult;
    
    public function resolveConflict(
        ScheduleItem $existing,
        ScheduleItemData $new,
        Lesson $newLesson
    ): ?ConflictResult;
}
```

### 1.2. TimelineManager (Helper Service)

**Dosya:** `App/Services/Helpers/TimelineManager.php`

**Sorumluluklar:**
- Timeline flattening
- Saat aralığı hesaplamaları
- Slot intersection detection

**Mevcut Kod:**
- `ScheduleController::flattenTimeline()` - L1573-1627
- `ScheduleController::timeInRange()` - L1629-1638

**Refactoring:**
```php
class TimelineManager {
    public function flattenTimeline(
        array $items,
        string $newStart,
        string $newEnd
    ): array;
    
    public function timeInRange(
        string $time,
        string $start,
        string $end
    ): bool;
}
```

### 1.3. Multi-Schedule Kaydetme

**Amaç:** Bir schedule item kaydedildiğinde ilgili tüm owner'ların schedule'larına kaydet.

**Owners:**
- Program schedule (mevcut - çalışıyor)
- Lesson schedule
- Lecturer (user) schedule
- Classroom schedule

**Implementation:**
```php
// ScheduleService::saveScheduleItems()
private function determineOwners(ScheduleItemData $item): array {
    // Lesson'dan owner'ları belirle
    // Return: [
    //   ['type' => 'program', 'id' => 531],
    //   ['type' => 'lesson', 'id' => 502],
    //   ['type' => 'user', 'id' => 146],
    //   ['type' => 'classroom', 'id' => 1]
    // ]
}

private function saveToMultipleSchedules(
    ScheduleItemData $itemData,
    array $owners
): array {
    $createdIds = [];
    foreach ($owners as $owner) {
        $schedule = $this->scheduleRepo->findOrCreateSchedule($owner);
        $item = $this->scheduleItemRepo->create([
            'schedule_id' => $schedule->id,
            // ... diğer alanlar
        ]);
        $createdIds[] = $item->id;
    }
    return $createdIds;
}
```

### 1.4. Group Item Merge Mantığı

**Amaç:** Group item'lar eklenirken çakışan item'ları merge et.

**Mevcut Kod:**
- `ScheduleController::processGroupItemSaving()` - L924-1020

**Implementation:**
```php
private function mergeGroupItems(
    ScheduleItemData $newItem,
    array $existingItems
): ScheduleItemData {
    // Mevcut group item'ların data'sını birleştir
    // Yeni item'ın data'sını ekle
    // Tek bir ScheduleItem olarak kaydet
}
```

### 1.5. Delete Operations

**Mevcut Kod:**
- `ScheduleController::deleteScheduleItems()` - L1022-1152

**Implementation:**
```php
public function deleteScheduleItems(
    array $itemsData,
    bool $includeRelatedLessons = true
): DeleteScheduleResult;
```

---

## Bölüm 2: Diğer Ana Servisler

### 2.1. LessonService

**Dosya:** `App/Services/LessonService.php`

**Sorumluluklar:**
- Ders CRUD operations
- Child lesson yönetimi
- Lesson hour hesaplamaları
- Lesson metadata (program, lecturer, vb.)

**Metotlar:**
```php
class LessonService extends BaseService {
    private LessonRepository $lessonRepo;
    
    public function createLesson(array $data): Lesson;
    public function updateLesson(int $id, array $data): Lesson;
    public function deleteLesson(int $id): bool;
    
    public function connectChildLesson(int $parentId, int $childId): void;
    public function disconnectChildLesson(int $childId): void;
    
    public function calculateTotalHours(Lesson $lesson, string $type): int;
    public function isScheduleComplete(Lesson $lesson, string $type): bool;
}
```

**Mevcut Kod (Controller'dan taşınacak):**
- `LessonController::saveNew()` - İş kuralları service'e
- `LessonController::update()` - İş kuralları service'e
- Model'deki iş mantıkları

### 2.2. UserService

**Dosya:** `App/Services/UserService.php`

**Sorumluluklar:**
- Kullanıcı CRUD
- Authentication (login/logout)
- Password yönetimi
- Role/Permission kontrolü

**Metotlar:**
```php
class UserService extends BaseService {
    private UserRepository $userRepo;
    
    public function createUser(array $data): User;
    public function updateUser(int $id, array $data): User;
    public function deleteUser(int $id): bool;
    
    public function login(string $username, string $password): ?User;
    public function logout(): void;
    public function changePassword(int $userId, string $newPassword): bool;
}
```

### 2.3. ClassroomService

**Dosya:** `App/Services/ClassroomService.php`

**Sorumluluklar:**
- Derslik CRUD
- Derslik müsaitlik kontrolü
- Kapasite kontrolü

**Metotlar:**
```php
class ClassroomService extends BaseService {
    private ClassroomRepository $classroomRepo;
    
    public function createClassroom(array $data): Classroom;
    public function updateClassroom(int $id, array $data): Classroom;
    public function deleteClassroom(int $id): bool;
    
    public function findAvailableClassrooms(
        string $dayIndex,
        string $startTime,
        string $endTime,
        int $minCapacity
    ): array;
}
```

---

## Bölüm 3: Controller Entegrasyonları

### 3.1. LessonController Entegrasyonu

```php
public function saveNew(array $data): array {
    if (FeatureFlags::useNewLessonService()) {
        $service = new LessonService();
        $lesson = $service->createLesson($data);
        return ['id' => [$lesson->id]];
    }
    return $this->legacySaveNew($data);
}
```

### 3.2. UserController Entegrasyonu

```php
public function login(string $username, string $password): ?User {
    if (FeatureFlags::useNewUserService()) {
        $service = new UserService();
        return $service->login($username, $password);
    }
    return $this->legacyLogin($username, $password);
}
```

### 3.3. ClassroomController Entegrasyonu

```php
public function saveNew(array $data): array {
    if (FeatureFlags::useNewClassroomService()) {
        $service = new ClassroomService();
        $classroom = $service->createClassroom($data);
        return ['id' => [$classroom->id]];
    }
    return $this->legacySaveNew($data);
}
```

---

## Öncelik Sırası

### Hafta 1: ScheduleService Tamamlama
1. **Gün 1-2:** ConflictResolver + TimelineManager
2. **Gün 3-4:** Multi-schedule kaydetme
3. **Gün 5:** Group item merge mantığı

### Hafta 2: Diğer Servisler
1. **Gün 1-2:** LessonService + entegrasyon
2. **Gün 3:** UserService + entegrasyon
3. **Gün 4:** ClassroomService + entegrasyon
4. **Gün 5:** Code review + dokümantasyon

---

## Çıktılar

**Tamamlanacak Dosyalar:**
- `App/Services/Helpers/ConflictResolver.php`
- `App/Services/Helpers/TimelineManager.php`
- `App/Services/ScheduleService.php` (güncellenecek)
- `App/Services/LessonService.php`
- `App/Services/UserService.php`
- `App/Services/ClassroomService.php`
- `App/Repositories/LessonRepository.php`
- `App/Repositories/UserRepository.php`
- `App/Repositories/ClassroomRepository.php`

**Güncellenecek Controller'lar:**
- `ScheduleController.php` (helper service kullanımı)
- `LessonController.php`
- `UserController.php`
- `ClassroomController.php`

**Feature Flags:**
- `use_new_lesson_service`
- `use_new_user_service`
- `use_new_classroom_service`

---

## Risk Yönetimi

**Yüksek Risk:**
- Multi-schedule kaydetme - performans
- Group item merge - karmaşık mantık

**Mitigation:**
- Adım adım implement et
- Her adımda test et (manual)
- Feature flag ile geri dönüş

**Düşük Risk:**
- LessonService, UserService, ClassroomService - basit CRUD

---

## Sonraki Adım

Kullanıcıdan onay alındıktan sonra:
1. ConflictResolver ile başla
2. Her helper service için ayrı commit
3. Multi-schedule kaydetme implement et
