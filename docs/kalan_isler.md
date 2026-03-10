# 📋 Kalan İşler — Schedule Maker Mimari Dönüşüm

> Oluşturulma: 2026-03-05  
> Bu dosya tamamlanan dönüşümden sonra geciktirilmiş / sonraya bırakılan işleri listeler.

---

## 🧪 1. Unit & Integration Testler (Öncelikli)

Tüm servisler için test yazılmalı:

| Servis | Test Tipi | Notlar |
|--------|-----------|--------|
| `ScheduleService` | Unit + Integration | `saveScheduleItems`, `deleteScheduleItems`, `wipeResourceSchedules` |
| `ExamService` | Unit + Integration | `saveExamScheduleItems`, `availableObservers` |
| `ConflictService` | Unit | `checkScheduleCrash` |
| `AvailabilityService` | Unit | `availableClassrooms` |
| `LessonService` | Unit | `combineLesson` (karmaşık — schedule sync) |
| `UserService` | Unit | `login`, `saveNew` (password hash) |
| `ClassroomService` | Unit | `saveNew`, `updateClassroom` |

**Araç önerisi:** PHPUnit — `tests/Unit/Services/` ve `tests/Integration/` dizinleri oluşturulmalı.

---

## 🔧 2. ScheduleController Temizliği (Orta Öncelik)

`ScheduleController` hâlâ hem view/HTML render hem de iş mantığı içeriyor.

**Tespit edilen temizlik alanları:**

- `generateTimesArrayFromText()` → `// todo yeni tablo düzenine göre düzenlenecek` notu var
- `createScheduleExcelTable()` → `// todo yeni tablo düzenine göre düzenlenecek` notu var  
- `availableLessons()` → `AvailabilityService`'e taşınabilir (zaten `AvailabilityService` var)
- `saveScheduleItems()` + `formatServiceResultToLegacy()` → `ScheduleService` kullanıyor ama wrapper mevcut
- `prepareScheduleRows()`, `prepareScheduleCard()`, `getSchedulesHTML()`, `getAvailableLessonsHTML()` → Bunlar view render — controller'da kalabilir veya ayrı bir `ScheduleRenderer` sınıfına çıkarılabilir

---

## 🗃️ 3. Repository Pattern Tamamlama (Düşük Öncelik)

Mevcut: `ScheduleRepository`, `ScheduleItemRepository`, `BaseRepository`

Eksik:
- `LessonRepository`
- `UserRepository`
- `ClassroomRepository`

Şu an servisler direkt model kullanıyor — repository pattern uygulanınca servislerdeki `(new Lesson())->where(...)` çağrıları repository'ye taşınmalı.

---

## 🏷️ 4. Enum ve Type Safety (Düşük Öncelik)

- `ScheduleItemStatus` enum → `'single' | 'group' | 'preferred' | 'unavailable'`
- `ScheduleType` enum → `'lesson' | 'exam_midterm' | 'exam_final' | 'exam_makeup'`
- `OwnerType` enum → `'lesson' | 'user' | 'classroom' | 'program'`

---

## 📝 5. AdminRouter / View Data Temizliği (Düşük Öncelik)

AdminRouter view render için controller'lar kullanmaya devam ediyor:
- `ClassroomController::getClassroomsList()` → Direkt model sorgusu yapılabilir
- `UserController::getRoleList()`, `getTitleList()` → Config/enum'a taşınabilir
- `ScheduleController` view'lara pass ediliyor → İlerleyen süreçte kaldırılabilir

---

## 🔍 6. PHPStan / Static Analysis (Düşük Öncelik)

Mimari dönüşüm tamamlandıktan sonra PHPStan level 5+ çalıştırılmalı.

---

## ✅ Tamamlanan İşler (Referans)

- [x] Faz 1: BaseService, BaseRepository, ErrorHandler altyapısı
- [x] Faz 2: ScheduleService, DTOs, ScheduleItemValidator
- [x] Faz 3: ExamService, ConflictService, AvailabilityService, ConflictResolver, TimelineManager
- [x] Validation sadeleştirme (FilterValidator → AjaxRouter direkt)
- [x] FeatureFlags tamamen kaldırıldı
- [x] Faz 4: LessonService, UserService, ClassroomService
- [x] Controller temizliği: Servis'e taşınan metotlar silindi
- [x] AjaxRouter: Tüm iş mantığı çağrıları servis'lere yönlendirildi
- [x] availableLessons() → AvailabilityService'e taşındı
- [x] checkLecturerScheduleAction, checkClassroomScheduleAction, checkProgramScheduleAction logic → AvailabilityService'e taşındı
