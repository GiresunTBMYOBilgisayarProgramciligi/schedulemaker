[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Services](../README.md) / **Export**

---
# App\Services\Export — Dışa Aktarma Servisleri

Program dışa aktarma işlemlerini yöneten servis katmanı. **Strategy** ve **Factory** tasarım desenleri kullanılarak yapılandırılmıştır.

## Klasör Yapısı

```
App/Services/Export/
├── ScheduleExporterInterface.php   # Tüm exporter sınıflarının uyduğu arayüz
├── ExporterFactory.php             # Doğru exporter'ı üreten fabrika
├── ScheduleFilterBuilder.php       # Schedule sorgusu için filtre listesi üretir
├── Excel/
│   ├── BaseExcelExporter.php       # Ortak Excel altyapısı (abstract)
│   ├── LessonScheduleExcelExporter.php  # Ders programı → .xlsx
│   └── ExamScheduleExcelExporter.php   # Sınav programı → .xlsx
└── Ics/
    ├── BaseIcsExporter.php         # Ortak ICS altyapısı (abstract)
    ├── LessonScheduleIcsExporter.php    # Ders programı → .ics
    └── ExamScheduleIcsExporter.php     # Sınav programı → .ics
```

## ExporterFactory Kullanımı

```php
use App\Services\Export\ExporterFactory;

$exporter = ExporterFactory::create($filters, 'excel'); // veya 'ics'
$exporter->export($filters, $showOptions);
```

`$filters['type']` değerine göre doğru exporter seçilir:
- `lesson` → `LessonSchedule*Exporter`
- `midterm-exam`, `final-exam`, `makeup-exam` → `ExamSchedule*Exporter`

## showOptions Parametresi

```php
$showOptions = [
    'show_code'     => true,   // Ders kodu
    'show_lecturer' => true,   // Hoca adı
    'show_program'  => true,   // Program/Bölüm adı
    'show_observer' => true,   // Gözetmen isimleri (sadece sınav programları)
];
```

## ScheduleFilterBuilder

`generateScheduleFilters()` mantığının bağımsız sınıfı. `owner_type` değerine göre (`program`, `department`, `user`, `classroom`, `lesson`) gerekli Schedule sorgulama filtrelerini üretir.

```php
$builder = new ScheduleFilterBuilder();
$filters = $builder->build($requestFilters);
// Her eleman: ['file_title', 'title', 'type', 'filter']
```

## Sınav Programında Veri Yapısı

Sınav programı kayıtları (`schedule_items`) iki farklı tipte olabilir:

### A) Program / Ders bazlı kayıt
Bölüm veya program sekmesinden yapılan sınav export'u bu kayıtları okur.
- `data`: `[{lesson_id: X, lecturer_id: null, classroom_id: null}]`
- `detail`: `{assignments: [{observer_id, observer_name, classroom_id, classroom_name}, ...]}`

Gözetmenler ve derslikler `detail.assignments` üzerinden okunur — `getSlotDatas()` döndürmez.

### B) Gözetmen / Derslik bazlı kayıt
Hoca veya derslik sekmesinden yapılan sınav export'u bu kayıtları okur.
- `data`: `[{lesson_id: X, lecturer_id: Y, classroom_id: Z}]`
- `detail`: `{program_item_id: ..., reference_type: 'exam_assignment'}`

Bu kayıtlarda `getSlotDatas()` `lecturer` ve `classroom` alanlarını dolu döndürür.

> [!IMPORTANT]
> `ExamScheduleExcelExporter` ve `ExamScheduleIcsExporter` sınıfları bu iki tipi otomatik ayırt eder.
> `detail['assignments']` varsa A tipi, `detail['reference_type'] === 'exam_assignment'` ise B tipi olarak işlenir.

## Excel Export Özellikleri

| Özellik | Ders (`Lesson`) | Sınav (`Exam`) |
|---|---|---|
| Başlık rengi | Turuncu (`ffbf00`) | Mavi (`4472C4`) |
| Belge başlığı | "HAFTALIK DERS PROGRAMI" | "ARA SINAV / FİNAL / BÜTÜNLEME PROGRAMI" |
| Çoklu hafta | Hayır | Final için Evet (2 hafta) |
| S sütunu etiketi | "S" (Sınıf) | "S / Gözetmen" |
| Gözetmen desteği | Hayır | Evet (show_observer) |

## ICS Export Özellikleri

| Özellik | Ders (`Lesson`) | Sınav (`Exam`) |
|---|---|---|
| Tekrar kuralı (RRULE) | Haftalık (dönem boyunca) | Yok (tek seferlik) |
| Tarih hesabı | Dönem başlangıcından itibaren | week_index + dönem başlangıcı |
| Başlık formatı | "Ders adı (Kod)" | "[Sınav Türü] Ders adı (Kod)" |
| Açıklama (description) | Hoca, Program, Dönem | Hoca, Program, Gözetmenler, Sınav Türü, Dönem |
