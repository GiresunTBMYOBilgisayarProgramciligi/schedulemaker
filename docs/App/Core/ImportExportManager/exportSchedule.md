[🏠 Ana Sayfa](../../../../README.md) / [App](../../../README.md) / [Core](../../README.md) / **ImportExportManager**

---
# App\Core\ImportExportManager

> [!WARNING]
> Bu sınıf **kullanım dışı (deprecated)** olarak işaretlenmiştir ve artık aktif olarak kullanılmamaktadır.
> Tüm işlevsellik `App/Services` altındaki özel servis sınıflarına taşınmıştır.
> Bu dosya gelecekte silinecektir.

## Yeni Servis Yapısı

### İçe Aktarma (Import)

| Eski Metod | Yeni Sınıf |
|---|---|
| `importUsersFromExcel()` | [`App\Services\Import\UserImporter`](../../../Services/Import/UserImporter.php) |
| `importLessonsFromExcel()` | [`App\Services\Import\LessonImporter`](../../../Services/Import/LessonImporter.php) |

### Dışa Aktarma (Export)

| Eski Metod | Yeni Sınıf |
|---|---|
| `exportSchedule()` | `App\Services\Export\Excel\LessonScheduleExcelExporter` (ders) |
| `exportSchedule()` | `App\Services\Export\Excel\ExamScheduleExcelExporter` (sınav) |
| `exportScheduleIcs()` | `App\Services\Export\Ics\LessonScheduleIcsExporter` (ders) |
| `exportScheduleIcs()` | `App\Services\Export\Ics\ExamScheduleIcsExporter` (sınav) |
| `generateScheduleFilters()` | [`App\Services\Export\ScheduleFilterBuilder`](../../../Services/Export/ScheduleFilterBuilder.php) |

### Doğru Kullanım

```php
// Eski (kullanım dışı):
$mgr = new ImportExportManager();
$mgr->exportSchedule($filters);

// Yeni:
$exporter = ExporterFactory::create($filters, 'excel'); // veya 'ics'
$exporter->export($filters, $showOptions);
```

`ExporterFactory`, `$filters['type']` değerine göre (`lesson`, `midterm-exam`, `final-exam`, `makeup-exam`) otomatik olarak doğru sınıfı seçer.
