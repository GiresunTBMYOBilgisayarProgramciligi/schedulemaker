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

## 🏷️ 4. Enum ve Type Safety (Düşük Öncelik)

- `ScheduleItemStatus` enum → `'single' | 'group' | 'preferred' | 'unavailable'`
- `OwnerType` enum → `'lesson' | 'user' | 'classroom' | 'program'`

---

## 🔍 6. PHPStan / Static Analysis (Düşük Öncelik)

Mimari dönüşüm tamamlandıktan sonra PHPStan level 5+ çalıştırılmalı.
