[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / **Classroom**

---
# App\Models\Classroom & Department

## App\Models\Classroom
Derslikleri temsil eden modeldir.
*   **Özellikler**: `id`, `name`, `type` (lab, normal, uzem), `class_size`, `exam_size`.
*   **İlişkiler**: `schedule_items` ile bağlıdır (Hangi saatlerde dolu olduğu).

## App\Models\Department
Fakültedeki bölümleri temsil eden modeldir.
*   **Özellikler**: `id`, `name`, `chairperson_id`, `active`.
*   **İlişkiler**: `programs` (HasMany) ve `users` (HasMany) ile bağlıdır.
*   **Metodlar**: `getChairperson()` metodu ile bölüm başkanını döner.
