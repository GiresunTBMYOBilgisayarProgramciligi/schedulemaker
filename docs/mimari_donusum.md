# 🎯 Proje Master Plan - Schedule Maker

**Proje:** Schedule Maker Mimari Dönüşüm  
**Başlangıç:** 2026-02-12  
**Son Güncelleme:** 2026-02-27  
**Durum:** Faz 3 Tamamlandı → Faz 4 Bekliyor

---

## 🏗️ Hedef Mimari

```
HTTP Request
    │
    ▼
[Router] → Gate::authorize()          ← Yetki (Policies)
    │
    ▼
[Router] → FilterValidator            ← Input sanitization
    │
    ▼
[Service] → ScheduleItemValidator     ← Business rules
    │
    ▼
[Repository] → DB
```

**Prensipleri:**
- Thin Controllers — Sadece routing ve response
- Service Layer — İş mantığı
- Repository Layer — Veri erişimi

---

## 📊 Faz Durumları

### ✅ Faz 1: Altyapı (TAMAMLANDI)
- [x] `BaseService` — Transaction yönetimi, logging
- [x] `BaseRepository` — CRUD base
- [x] `FeatureFlags` — Feature toggle
- [x] `Log` helper — Context-aware logging
- [x] `ErrorHandler` — Global exception handling

### ✅ Faz 2: ScheduleService (TAMAMLANDI)
- [x] `ScheduleItemData`, `SaveScheduleResult`, `DeleteScheduleResult` DTOs
- [x] `ScheduleItemValidator` — Batch validation
- [x] `ScheduleRepository`, `ScheduleItemRepository`
- [x] `ScheduleService` — Multi-schedule kaydetme, delete, child lesson fix

### ✅ Faz 3: Service Layer Genişletme (TAMAMLANDI)
**Helper Servisler:**
- [x] `ConflictResolver` — Çakışma kontrolü
- [x] `TimelineManager` — Saat aralığı hesaplamaları, flatten timeline

**Sınav / Ortak Servisler:**
- [x] `ExamService` — saveExamScheduleItems, availableObservers, findExamSiblingItems, determineExamOwners
- [x] `ConflictService` — checkScheduleCrash (ders + sınav)
- [x] `AvailabilityService` — availableClassrooms (ders + sınav)

**Validation Sadeleştirme:**
- [x] `FilterValidator` — ScheduleController üzerinden erişim kaldırıldı, AjaxRouter direkt kullanıyor
- [x] `ScheduleController` — `public validator` property silindi (1046 → 579 satır)
- [x] `docs/App/validation.md` — 3 katman (FilterValidator, ScheduleItemValidator, Policies) dokümante edildi

**Exception & Logging:**
- [x] `AppException::__toString()` — context otomatik log'a ekleniyor
- [x] `ValidationException` — hata listesi hem mesajda hem context'te

---

### 🔄 Faz 4: Diğer Ana Servisler (SIRADAKI)

Bu 3 servis oluşturulduğunda LessonController, UserController, ClassroomController da ince dispatcher'a dönüşecek:

- [x] **LessonService** — saveNew, updateLesson, combineLesson (schedule sync dahil), deleteParentLesson
  - `createLesson`, `updateLesson`, `deleteLesson`
  - `connectChildLesson`, `disconnectChildLesson`
  - `calculateTotalHours`, `isScheduleComplete`
- [x] **UserService** — saveNew (password_hash), updateUser, login (session/cookie/last_login)
- [ ] **ClassroomService** — Derslik CRUD + müsaitlik
  - `createClassroom`, `updateClassroom`, `deleteClassroom`
  - `findAvailableClassrooms`
- [x] LessonController → LessonService entegrasyonu
- [x] UserController → UserService entegrasyonu
- [ ] ClassroomController → ClassroomService entegrasyonu

---

### ⏸️ Faz 5: Test + Legacy Temizlik (PLANLI)
- [ ] Unit testler (servisler için öncelik)
- [ ] Feature flag'leri kaldırma
- [ ] ScheduleController legacy metot temizliği

---

### 💡 Faz 6: İleri İyileştirmeler (İLERİDE)

Bunlar şu an gerekli değil, ileride değerlendirilebilir:

- **Database Indexing** — `schedule_items` ve `schedules` için composite index'ler
- **Batch Query Optimizasyonu** — N+1 sorunu, `owner_type IN (...)` batch sorgular
- **Strategy Pattern** — ConflictResolver için genişletilebilir strateji yapısı
- **Enum Kullanımı** — `ScheduleItemStatus` enum (single, group, preferred, unavailable)
- **Event-Driven Architecture** — `ScheduleItemCreated` event, listener'lar (ileride, opsiyonel)
- **Queue/Async** — Büyük batch işlemler için (ileride, opsiyonel)
- **Optimistic Locking** — Concurrent update koruması (ileride)
- **PHPStan Level 6+** — Static analysis

---

## 📁 Mevcut Yapı

```
App/Services/
├── BaseService.php
├── ScheduleService.php       ← tam fonksiyonel
├── ExamService.php           ← sınav işlemleri
├── ConflictService.php       ← çakışma kontrolü
├── AvailabilityService.php   ← müsaitlik
└── Helpers/
    ├── ConflictResolver.php
    └── TimelineManager.php

App/Repositories/
├── BaseRepository.php
├── ScheduleRepository.php
└── ScheduleItemRepository.php

App/Validators/
├── BaseValidator.php
├── ScheduleItemValidator.php
└── ValidationResult.php

App/Policies/          ← Aktif (Gate ile kullanılıyor)
App/DTOs/              ← ScheduleItemData, SaveScheduleResult, DeleteScheduleResult
App/Exceptions/        ← AppException, ValidationException, vb.
```

---

## 📚 Aktif Dökümanlar

| Döküman | Açıklama |
|---------|----------|
| [docs/App/validation.md](App/validation.md) | Validation katmanları rehberi |
| [ders_programi_algoritmasi.md](ders_programi_algoritmasi.md) | Algoritma detayları |
| [exception_logging best practices](#faz-6-ileri-iyilestirmeler) | Bu dosyaya entegre edildi |
