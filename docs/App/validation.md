# Validation Altyapısı

Projede üç bağımsız doğrulama/yetkilendirme mekanizması bulunur. Her biri farklı bir katmanda çalışır ve birbirini tamamlar.

---

## 1. FilterValidator — HTTP Input Sanitization

**Dosya:** `App/Helpers/FilterValidator.php`  
**Katman:** Router → Controller girişi

### Görevi
Gelen ham HTTP verisini (`$_POST`, `$_GET`) güvenli ve tiplenmiş filtrelere dönüştürür:
- **Whitelist:** Bilinmeyen alanları atar
- **Zorunlu alan kontrolü:** Eksik alan varsa `InvalidArgumentException` fırlatır
- **Tip normalizasyonu:** `'42'` → `42`
- **Default değer enjeksiyonu:** Eksik optional alanlara `semester`, `academic_year` gibi ayar değerlerini ekler

### Doğru Kullanım
```php
// AjaxRouter içinde, servis çağrısından ÖNCE:
$filters = (new FilterValidator())->validate($this->data, "availableClassrooms");
$service = new AvailabilityService();
$classrooms = $service->availableClassrooms($filters);
```

### Yanlış Kullanım (kaldırıldı)
```php
// YANLIŞ — Controller üzerinden erişim
$scheduleController = new ScheduleController();
$filters = $scheduleController->validator->validate($this->data, "availableClassrooms");
```

### MasterSchema Alanları
| Alan | Tip | Açıklama |
|------|-----|----------|
| `type` | string | Program tipi (lesson, midterm-exam...) |
| `owner_type` | string | user, program, classroom, lesson |
| `owner_id` | int | Owner ID |
| `schedule_id` | int | Program ID |
| `lesson_id` | int | Ders ID |
| `semester` | string | Dönem (Güz, Bahar) |
| `academic_year` | string | Akademik yıl (2024-2025) |
| `semester_no` | int\|int[] | Yarıyıl numarası |
| `day_index` | int | Gün index (0-6) |
| `week_index` | int | Hafta index |
| `items` | string | JSON formatında schedule items |
| `start_time` | string | Başlangıç saati (H:i) |
| `end_time` | string | Bitiş saati (H:i) |

---

## 2. BaseValidator / ScheduleItemValidator — Business Validation

**Dosya:** `App/Validators/`  
**Katman:** Service → Repository

### Görevi
İş kurallarını doğrular — FilterValidator'dan geçmiş temiz veri üzerinde çalışır:
- Zaman formatı (`HH:MM`)
- Aralık kontrolü (`day_index` 0-6)
- İlişki tutarlılığı (`start_time < end_time`)
- Status geçerliliği (single, group, preferred, unavailable)

### Kullanım
```php
// ScheduleService içinde:
$validationResult = $this->validator->validateBatch($itemsData);
if (!$validationResult->isValid) {
    throw new ValidationException($validationResult->getErrorsAsString());
}
```

### ValidationResult
`readonly class` — immutable sonuç nesnesi:
```php
ValidationResult::success()
ValidationResult::failed(['start_time geçersiz', 'day_index aralık dışı'])
ValidationResult::failedWithError('Tek hata mesajı')
$result->isValid        // bool
$result->errors         // string[]
$result->getErrorsAsString()  // birleştirilmiş string
```

### Genişletme
Yeni bir validator için:
1. `App/Validators/XxxValidator.php` oluştur, `BaseValidator` extend et
2. `validate(array $data): ValidationResult` metodunu implement et
3. `BaseValidator`'daki helper metodları kullan: `isValidTimeFormat()`, `isInRange()`, `isEmpty()`

---

## 3. Policies + Gate — Authorization (Kim Yapabilir?)

**Dosya:** `App/Policies/`  
**Katman:** Router → İstek başlangıcı

### Görevi
Kimlik doğrulaması yapılmış kullanıcının belirli bir işlemi yapıp yapamayacağını kontrol eder.
Veri doğrulamayla ilgisi yoktur — sadece yetki kontrolü yapar.

### Kullanım Örnekleri
```php
// Rol tabanlı yetki
Gate::authorizeRole("department_head", false, "Yetkiniz yok");

// Nesne tabanlı yetki (Policy kullanır)
Gate::authorize("delete", $lesson, "Bu dersi silme yetkiniz yok");
Gate::authorize("update", $schedule, "Program güncelleme yetkiniz yok");
```

### Policy Sınıfları
| Policy | Eylemler |
|--------|----------|
| `LessonPolicy` | list, view, create, update, delete, combine |
| `SchedulePolicy` | update, delete |
| `ClassroomPolicy` | create, update, delete |
| `DepartmentPolicy` | create, update, delete |
| `ProgramPolicy` | create, update, delete |
| `UserPolicy` | create, update, delete, resetPassword |

**`BasePolicy::before()`:** Admin kullanıcılar her zaman yetkili.

---

## Katman Sırası

```
HTTP Request
    │
    ▼
[AjaxRouter] → Gate::authorize*()      ← Yetki
    │
    ▼
[AjaxRouter] → FilterValidator          ← Input sanitization
    │
    ▼
[Service]    → ScheduleItemValidator    ← Business rules
    │
    ▼
[Repository] → DB
```

---

## Önemli Kurallar

1. **FilterValidator**, ScheduleController üzerinden **kullanılmaz** — doğrudan `new FilterValidator()` ile kullanılır
2. **ScheduleItemValidator** router'da değil, servis katmanında çalışır
3. **Policies** sadece yetki için kullanılır, veri doğrulama yapmaz
4. Yeni bir AjaxRouter action'ında sıra: Gate → FilterValidator → Service
