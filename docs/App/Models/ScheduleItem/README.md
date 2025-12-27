[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / **ScheduleItem**

---
# App\Models\ScheduleItem

`ScheduleItem`, takvim üzerindeki her bir atomik ders veya sınav bloğunu temsil eder.

## Özellikler

*   `id`, `schedule_id`: Bağlı olduğu takvim.
*   `day_index`: Haftanın günü (0-6).
*   `start_time`, `end_time`: Blok başlangıç ve bitiş saatleri.
*   `status`: 'single', 'group', 'preferred', 'unavailable'.
*   `data`: JSON formatında ders ID'leri ve hoca detayları.
*   `detail`: JSON formatında ek bilgiler (Derslik tipi vb.).

## İlişkiler

1.  **Schedule**: `belongsTo`.
2.  **Lesson(s)**: `data` içindeki ID'ler üzerinden ilişkili ders(ler).

## Kritik Metodlar

*   [getShortStartTime()](./getShortStartTime.md): Zamanı HH:mm formatında kısaltır.
*   [isConflict()](./isConflict.md): Başka bir item ile çakışma durumunu kontrol eder.
