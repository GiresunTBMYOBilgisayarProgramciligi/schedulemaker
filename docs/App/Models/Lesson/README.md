[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / **Lesson**

---
# App\Models\Lesson

`Lesson` modeli, eğitim müfredatındaki dersleri temsil eder.

## Özellikler (Properties)

*   `id`: Primary Key.
*   `code`: Ders kodu (örn: BLP101).
*   `name`: Dersin tam adı.
*   `hours`: Haftalık saat yükü.
*   `group_no`: Dersin dahil olduğu grup numarası.
*   `classroom_type`: Gereken sınıf tipi (Lab, Normal, Uzem).

## İlişkiler

1.  **Lecturer (User)**: `belongsTo`. Dersi veren hoca ile ilişkilidir.
2.  **Program**: `belongsTo`. Dersin bağlı olduğu bölüm/program.
3.  **Schedules**: `hasMany`. Bu derse ait olan tüm program kayıtları.

## Kritik Metodlar

*   [IsScheduleComplete()](./IsScheduleComplete.md): Ders programının saat bazında tamamlanıp tamamlanmadığını kontrol eder.
