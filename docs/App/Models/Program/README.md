[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / **Program**

---
# App\Models\Program & Schedule

## App\Models\Program
Bölümlere bağlı programları (örn: Bilgisayar Programcılığı) temsil eder.
*   **Özellikler**: `id`, `name`, `department_id`.
*   **İlişkiler**: `lessons` (HasMany) ve `schedules` (HasMany) ile bağlıdır.

## App\Models\Schedule
Ders programlarının veya sınav programlarının "başlık" bilgisini tutan modeldir.
*   **Özellikler**: `id`, `type` (lesson, midterm-exam vb.), `owner_type`, `owner_id`, `semester_no`, `academic_year`.
*   **İlişkiler**: `schedule_items` (HasMany) ile bağlıdır. Bir `Schedule` objesi silindiğinde bağlı olduğu tüm itemlar `CASCADE` ile silinir.
