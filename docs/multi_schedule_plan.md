# Multi-Schedule Kaydetme - Implementation Planı

## Genel Bakış

**Amaç:** Bir schedule item kaydedildiğinde, otomatik olarak ilgili tüm owner'ların schedule'larına kayıt yapılması.

**Owner Types:**
- **program** - Dersin bağlı olduğu program
- **lesson** - Dersin kendisi  
- **user** - Dersi veren öğretim üyesi
- **classroom** - Dersin yapıldığı derslik

> [!NOTE]
> **Gelecek Ayrım Önerisi:** LessonScheduleService ve ExamScheduleService olarak ayrılması planlanıyor.  
> Şu an ders ve sınav programları aynı service içinde, ancak mantık farklılıkları nedeniyle gelecekte ayrılması daha temiz olacak.

**Example Flow:**
```
User: "Pazartesi 09:00-10:50, Algorithm dersi, Ahmet Hoca, A101'de"
→ System creates 4 schedule items:
  1. Program Schedule (program_id=531, semester=3)
  2. Lesson Schedule (lesson_id=502)
  3. User Schedule (user_id=146, Ahmet Hoca)
  4. Classroom Schedule (classroom_id=1, A101)
```

---

## Mevcut Durum Analizi

### Controller'daki Owner Determination (L864-910)

```php
// ScheduleController::legacySaveScheduleItems()

$owners = [];
if ($isDummy) {
    // Preferred/Unavailable → Sadece target schedule'a kaydet
    $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
    $owners[] = ['type' => $targetSchedule->owner_type, 'id' => $targetSchedule->owner_id];
} else {
    $examAssignments = $itemData['detail']['assignments'] ?? null;
    if ($examAssignments) {
        // SINAV (Çoklu Atama)
        $owners[] = ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no];
        $owners[] = ['type' => 'lesson', 'id' => $lesson->id];
        foreach ($examAssignments as $assignment) {
            $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
            $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
        }
    } else {
        // NORMAL DERS
        $owners = [
            ['type' => 'user', 'id' => $lecturerId],
            ['type' => 'classroom', 'id' => ($lesson->classroom_type == 3) ? null : $classroomId],
            ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
            ['type' => 'lesson', 'id' => $lesson->id]
        ];
    }
    
    // Child Lessons
    if (!empty($lesson->childLessons)) {
        foreach ($lesson->childLessons as $childLesson) {
            $owners[] = ['type' => 'lesson', 'id' => $childLesson->id, 'is_child' => true];
            if ($childLesson->program_id) {
                $owners[] = ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no, 'is_child' => true];
            }
        }
    }
}
```

### Sorunlar

1. **Owner determination Controller'da** - Service'e taşınmalı
2. **Schedule creation logic dağınık** - Centralize edilmeli
3. **Child lesson handling karmaşık** - Ayrı metoda alınmalı
4. **UZEM dersleri için özel kural** (classroom_type == 3) → classroom schedule oluşturma

---

## Yeni Tasarım

### 1. ScheduleService'e Yeni Metotlar

#### 1.1. `determineOwners()`

```php
/**
 * Schedule item için ilgili tüm owner'ları (sahip programlar/kullanıcılar) belirler
 * 
 * Bu metod bir schedule item'ın hangi programlara, derslere, kullanıcılara ve dersliklere
 * ait olduğunu belirler. Her owner için ayrı bir schedule item oluşturulacaktır.
 * 
 * **Dummy Items (Preferred/Unavailable):**
 * Sadece ilgili target schedule'a kaydedilir (örn: bir hocanın tercih ettiği slot sadece o hocanın programına eklenir)
 * 
 * **Normal Ders:**
 * - Program schedule (dersin bağlı olduğu program)
 * - Lesson schedule (dersin kendisi)
 * - User schedule (öğretim üyesi)
 * - Classroom schedule (derslik, UZEM değilse)
 * 
 * **Sınav (Exam Assignments):**
 * Sınav atamaları ($dto->detail['assignments']) sınavda görevli gözlemciler ve kullanılacak derslikleri içerir.
 * Örnek: [{'observer_id': 146, 'classroom_id': 3}, {'observer_id': 152, 'classroom_id': 5}]
 * Her gözlemci ve derslik için ayrı schedule item oluşturulur.
 * 
 * @param ScheduleItemData $dto Schedule item verisi
 * @param Lesson|null $lesson İlgili ders entity'si (dummy items için null olabilir)
 * @return array Owner listesi, her biri ['type' => 'user|program|lesson|classroom', 'id' => int] formatında
 * @throws Exception Dummy olmayan item için lesson yoksa
 */
private function determineOwners(ScheduleItemData $dto, ?Lesson $lesson): array
{
    $owners = [];
    
    // Dummy items (preferred/unavailable) → Sadece target schedule
    if ($dto->isDummy()) {
        $targetSchedule = $this->scheduleRepo->find($dto->scheduleId);
        if ($targetSchedule) {
            return [[
                'type' => $targetSchedule->owner_type,
                'id' => $targetSchedule->owner_id,
                'semester_no' => $targetSchedule->semester ?? null
            ]];
        }
        return [];
    }
    
    // Normal ders/sınav
    if (!$lesson) {
        throw new Exception("Lesson required for non-dummy items");
    }
    
    // SINAV KONTROLÜ: detail->assignments varsa bu bir sınav programı demektir
    // assignments: Sınavda görevlendirilmiş gözlemciler ve kullanılacak derslikler
    // Örnek: [{'observer_id': 146, 'classroom_id': 3}, {'observer_id': 152, 'classroom_id': 5}]
    $examAssignments = $dto->detail['assignments'] ?? null;
    
    if ($examAssignments) {
        // SINAV - Çoklu gözlemci/derslik atamaları
        $owners = $this->determineExamOwners($lesson, $examAssignments);
    } else {
        // NORMAL DERS - Tek öğretim üyesi, tek derslik
        $owners = $this->determineLessonOwners($dto, $lesson);
    }
    
    // Child lessons dahil et (bağlı alt dersler varsa)
    if (!empty($lesson->childLessons)) {
        $childOwners = $this->determineChildLessonOwners($lesson->childLessons);
        $owners = array_merge($owners, $childOwners);
    }
    
    return $owners;
}
```

#### 1.2. `determineLessonOwners()`

```php
/**
 * Normal ders için owner listesini belirler
 * 
 * Bir normal ders için 4 owner olabilir:
 * 1. Program - Dersin bağlı olduğu program
 * 2. Lesson - Dersin kendisi
 * 3. User - Dersi veren öğretim üyesi
 * 4. Classroom - Dersin yapıldığı derslik (UZEM değilse)
 * 
 * **UZEM Kuralı:** 
 * classroom_type = 3 olan dersler UZEM (Uzaktan Eğitim) dersidir.
 * Bu dersler fiziksel derslik kullanmadığı için classroom schedule oluşturulmaz.
 * 
 * @param ScheduleItemData $dto Schedule item verisi, içinde lecturer_id ve classroom_id var
 * @param Lesson $lesson Ders entity'si, program_id ve classroom_type bilgilerini içerir
 * @return array Owner listesi [['type' => 'user|program|lesson|classroom', 'id' => int], ...]
 */
private function determineLessonOwners(ScheduleItemData $dto, Lesson $lesson): array
{
    $lecturerId = $dto->data['lecturer_id'] ?? null;
    $classroomId = $dto->data['classroom_id'] ?? null;
    
    $owners = [
        ['type' => 'user', 'id' => $lecturerId],
        ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
        ['type' => 'lesson', 'id' => $lesson->id]
    ];
    
    // UZEM dersleri için classroom schedule oluşturma
    // classroom_type: 1=Normal, 2=Lab, 3=UZEM
    if ($lesson->classroom_type != 3 && $classroomId) {
        $owners[] = ['type' => 'classroom', 'id' => $classroomId];
    }
    
    return $owners;
}
```

#### 1.3. `determineExamOwners()`

```php
/**
 * Sınav için owner listesini belirler
 * 
 * Sınav programları normal derslerden farklıdır:
 * - Bir sınavda birden fazla gözlemci olabilir
 * - Birden fazla derslik kullanılabilir
 * - Her gözlemci ve derslik için ayrı schedule item oluşturulur
 * 
 * **Exam Assignments Formatı:**
 * ```php
 * [
 *   ['observer_id' => 146, 'classroom_id' => 3],  // Ahmet Hoca, A101'de
 *   ['observer_id' => 152, 'classroom_id' => 5]   // Mehmet Hoca, B202'de
 * ]
 * ```
 * 
 * @param Lesson $lesson Sınav dersi entity'si
 * @param array $examAssignments Gözlemci-derslik atamaları
 * @return array Owner listesi, her assignment için user ve classroom owner'ı içerir
 */
private function determineExamOwners(Lesson $lesson, array $examAssignments): array
{
    $owners = [
        ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
        ['type' => 'lesson', 'id' => $lesson->id]
    ];
    
    // Her sınav ataması için gözlemci ve derslik owner'ı ekle
    foreach ($examAssignments as $assignment) {
        $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
        $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
    }
    
    return $owners;
}
```

#### 1.4. `determineChildLessonOwners()`

```php
/**
 * Bağlı alt dersler (child lessons) için owner listesini belirler
 * 
 * **Child Lesson Nedir?**
 * Bazı dersler başka derslere bağlıdır. Örneğin:
 * - "Veritabanı" dersi (parent) → Bilgisayar Programcılığı programına ait
 * - "Veritabanı-Lab" dersi (child) → Yönetim Bilişim Sistemleri programına ait
 * 
 * Parent ders programlandığında, child'ın da kendi programına eklenmesi gerekir.
 * 
 * **is_child Metadata:**
 * Child lesson owner'ları 'is_child' = true ve 'child_lesson_id' bilgisi taşır.
 * Bu sayede schedule item'da hangi child'a ait olduğu bilinir.
 * 
 * @param array $childLessons Child lesson entity'leri dizisi
 * @return array Owner listesi, her child için lesson ve (varsa) program owner'ı
 */
private function determineChildLessonOwners(array $childLessons): array
{
    $owners = [];
    
    foreach ($childLessons as $childLesson) {
        // Child lesson'un kendi schedule'ı
        $owners[] = [
            'type' => 'lesson',
            'id' => $childLesson->id,
            'is_child' => true,
            'child_lesson_id' => $childLesson->id
        ];
        
        // Child lesson'un programı varsa
        if ($childLesson->program_id) {
            $owners[] = [
                'type' => 'program',
                'id' => $childLesson->program_id,
                'semester_no' => $childLesson->semester_no,
                'is_child' => true,
                'child_lesson_id' => $childLesson->id
            ];
        }
    }
    
    return $owners;
}
```

#### 1.5. `findOrCreateSchedule()`

```php
/**
 * Belirtilen owner için schedule bulur, yoksa oluşturur
 * 
 * Schedule'lar akademik yıl, dönem ve tipe göre unique'tir:
 * - owner_type + owner_id + academic_year + semester + type → Unique constraint
 * 
 * **Örnek:**
 * - Ahmet Hoca (user_id=146)
 * - 2023-2024 Güz dönemi
 * - Ders programı (type='lesson')
 * → Bu kriterlere uyan schedule varsa kullan, yoksa oluştur
 * 
 * @param array $owner Owner bilgisi ['type' => 'user', 'id' => 146, 'semester_no' => 3]
 * @param string $academicYear Akademik yıl (örn: '2023-2024')
 * @param string $semester Dönem ('Güz', 'Bahar', 'Yaz')
 * @param string $type Schedule tipi ('lesson', 'midterm-exam', 'final-exam', 'makeup-exam')
 * @return Schedule Bulunan veya yeni oluşturulan schedule
 */
private function findOrCreateSchedule(
    array $owner,
    string $academicYear,
    string $semester,
    string $type
): Schedule {
    // Önce varolan schedule'ı ara
    $existing = $this->scheduleRepo->findByOwnerAndPeriod(
        $owner['type'],
        $owner['id'],
        $academicYear,
        $semester,
        $type,
        $owner['semester_no'] ?? null
    );
    
    if ($existing) {
        return $existing;
    }
    
    // Yoksa yeni schedule oluştur
    $schedule = new Schedule();
    $schedule->owner_type = $owner['type'];
    $schedule->owner_id = $owner['id'];
    $schedule->academic_year = $academicYear;
    $schedule->semester = $semester;
    $schedule->type = $type;
    
    // Program schedule'ları için semester_no gerekli
    if (isset($owner['semester_no'])) {
        $schedule->semester_no = $owner['semester_no'];
    }
    
    $schedule->create();
    
    return $schedule;
}
```

#### 1.6. `saveToMultipleSchedules()` (Ana Metot)

```php
/**
 * Schedule item'ı tüm ilgili owner'ların schedule'larına kaydeder
 * 
 * **İşlem Akışı:**
 * 1. Owner'ları belirle (determinedOwners)
 * 2. Her owner için:
 *    a. Schedule bul veya oluştur (findOrCreateSchedule)
 *    b. Schedule item oluştur ve kaydet
 * 3. Oluşturulan tüm item ID'lerini döndür
 * 
 * **Örnek:**
 * Input: Pazartesi 09:00-10:50, Algorithm dersi, Ahmet Hoca, A101
 * Output: [45, 46, 47, 48] → 4 ayrı schedule item ID'si
 * - ID 45: Program schedule item (Bilgisayar Programcılığı)
 * - ID 46: Lesson schedule item (Algorithm)
 * - ID 47: User schedule item (Ahmet Hoca)
 * - ID 48: Classroom schedule item (A101)
 * 
 * **Child Lesson Metadata:**
 * Child lesson'lar için oluşturulan item'larda detail['child_lesson_id'] bilgisi eklenir.
 * Bu sayede hangi child'a ait olduğu bilinir.
 * 
 * @param ScheduleItemData $dto Schedule item verisi
 * @param Lesson|null $lesson İlgili ders (dummy items için null)
 * @param Schedule $sourceSchedule Kaynak schedule (akademik yıl/dönem bilgisi için)
 * @return array Oluşturulan schedule item ID'leri
 */
private function saveToMultipleSchedules(
    ScheduleItemData $dto,
    ?Lesson $lesson,
    Schedule $sourceSchedule
): array {
    $owners = $this->determineOwners($dto, $lesson);
    $createdIds = [];
    
    foreach ($owners as $owner) {
        // Owner için schedule bul/oluştur
        $targetSchedule = $this->findOrCreateSchedule(
            $owner,
            $sourceSchedule->academic_year,
            $sourceSchedule->semester,
            $sourceSchedule->type
        );
        
        // Item oluştur
        $item = new ScheduleItem();
        $item->schedule_id = $targetSchedule->id;
        $item->day_index = $dto->dayIndex;
        $item->week_index = $dto->weekIndex;
        $item->start_time = $dto->startTime;
        $item->end_time = $dto->endTime;
        $item->status = $dto->status;
        $item->data = $dto->data;
        $item->detail = $dto->detail;
        
        // Child lesson metadata ekle
        if (isset($owner['is_child']) && $owner['is_child']) {
            $item->detail['child_lesson_id'] = $owner['child_lesson_id'];
        }
        
        $item->create();
        $createdIds[] = $item->id;
    }
    
    return $createdIds;
}
```

### 2. ScheduleRepository'ye Yeni Metot

```php
/**
 * Belirtilen owner ve dönem için schedule arar
 * 
 * Schedule'lar owner + dönem + tip kombinasyonu ile unique'tir.
 * Bu metod mevcut schedule'ı kontrol etmek için kullanılır.
 * 
 * **Unique Constraint:**
 * - owner_type + owner_id + academic_year + semester + type (+ semester_no)
 * 
 * **Kullanım Örnekleri:**
 * 
 * 1. Öğretim üyesi ders programı:
 *    findByOwnerAndPeriod('user', 146, '2023-2024', 'Güz', 'lesson')
 * 
 * 2. Program dersi (3. dönem):
 *    findByOwnerAndPeriod('program', 531, '2023-2024', 'Güz', 'lesson', 3)
 * 
 * 3. Derslik sınav programı:
 *    findByOwnerAndPeriod('classroom', 1, '2023-2024', 'Güz', 'final-exam')
 * 
 * @param string $ownerType Owner tipi ('user', 'program', 'lesson', 'classroom')
 * @param int $ownerId Owner ID'si
 * @param string $academicYear Akademik yıl (örn: '2023-2024')
 * @param string $semester Dönem ('Güz', 'Bahar', 'Yaz')
 * @param string $type Schedule tipi ('lesson', 'midterm-exam', 'final-exam', 'makeup-exam')
 * @param int|null $semesterNo Dönem numarası (sadece program schedule'lar için, opsiyonel)
 * @return Schedule|null Bulunan schedule veya null
 */
public function findByOwnerAndPeriod(
    string $ownerType,
    int $ownerId,
    string $academicYear,
    string $semester,
    string $type,
    ?int $semesterNo = null
): ?Schedule {
    $conditions = [
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'academic_year' => $academicYear,
        'semester' => $semester,
        'type' => $type
    ];
    
    // Program schedule'lar için semester_no da kontrol et
    if ($semesterNo !== null) {
        $conditions['semester_no'] = $semesterNo;
    }
    
    return $this->model->where($conditions)->first();
}
```

---

## Implementation Adımları

### Adım 1: ScheduleRepository Update
- `findByOwnerAndPeriod()` metodu ekle

### Adım 2: ScheduleService - Helper Metotlar
- `determineLessonOwners()`
- `determineExamOwners()`
- `determineChildLessonOwners()`
- `determineOwners()` (bunları çağıran)
- `findOrCreateSchedule()`

### Adım 3: ScheduleService - Ana Metot
- `saveToMultipleSchedules()` implement et
- `saveScheduleItems()` içinde çağır

### Adım 4: Refactor `saveScheduleItems()`
```php
public function saveScheduleItems(array $itemsData): SaveScheduleResult
{
    // ... validation ...
    
    foreach ($itemsData as $index => $itemData) {
        $dto = ScheduleItemData::fromArray($itemData);
        $schedule = $this->scheduleRepo->find($dto->scheduleId);
        $lesson = $this->getLesson($dto);
        
        // MULTI-SCHEDULE KAYDETME
        $itemIds = $this->saveToMultipleSchedules($dto, $lesson, $schedule);
        $createdIds = array_merge($createdIds, $itemIds);
        
        if ($lesson) {
            $affectedLessonIds[] = $lesson->id;
        }
    }
    
    // ... lesson hour check + commit ...
}
```

---

## Testing

### Test Scenario 1: Normal Ders
**Input:**
- Pazartesi 09:00-10:50
- Algorithm (lesson_id=502, program_id=531)
- Ahmet Hoca (user_id=146)
- A101 (classroom_id=1)

**Expected Output:**
4 schedule items created:
1. Program schedule (owner_type='program', owner_id=531)
2. Lesson schedule (owner_type='lesson', owner_id=502)
3. User schedule (owner_type='user', owner_id=146)
4. Classroom schedule (owner_type='classroom', owner_id=1)

### Test Scenario 2: Child Lesson
**Input:**
- Parent: Database (lesson_id=100, program_id=531)
- Child: Database-Lab (lesson_id=101, program_id=532)

**Expected Output:**
6 schedule items:
1-4. (Parent için 4 owner)
5. Child lesson schedule (owner_type='lesson', owner_id=101)
6. Child program schedule (owner_type='program', owner_id=532)

### Test Scenario 3: UZEM Dersi
**Input:**
- lesson.classroom_type = 3 (UZEM)

**Expected Output:**
3 schedule items (classroom schedule YOK):
1. Program
2. Lesson
3. User

---

## Backward Compatibility

**v1 Return Format:**
```php
return ['id' => [1, 2, 3, 4]]; // Tüm oluşturulan ID'ler
```

**v2 Return Format (gelecek):**
```php
return [
    'program' => [45],
    'lesson' => [46],
    'user' => [47],
    'classroom' => [48]
];
```

---

## Risks & Mitigation

**Risk 1:** Performance - 4x daha fazla DB insert  
**Mitigation:** Batch insert kullan (gelecekte), transaction yönetimi

**Risk 2:** Schedule creation overhead  
**Mitigation:** Cache existing schedules per request

**Risk 3:** Child lesson logic complexity  
**Mitigation:** Ayrı metoda izole et, unit test

---

## Next Steps

1. ScheduleRepository::findByOwnerAndPeriod() ekle
2. Helper metotları implement et
3. saveToMultipleSchedules() implement et
4. Test et (manual)
5. Legacy sistemle karşılaştır
