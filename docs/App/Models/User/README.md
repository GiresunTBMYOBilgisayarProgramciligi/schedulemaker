[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / **User**

---
# App\Models\User

`User` modeli, sistemdeki tüm kullanıcıları (Hoca, Admin vb.) temsil eder.

## Özellikler

*   `id`, `username`, `password`: Temel kimlik bilgileri.
*   `full_name`: Ad Soyad.
*   `role`: 'admin', 'lecturer' vb. yetki seviyeleri.
*   `department_id`: Hocanın bağlı olduğu ana bölüm.

## İlişkiler

1.  **Lessons**: `hasMany`. Hocanın sorumlu olduğu dersler.
2.  **Schedules**: `hasMany`. Hocanın kişisel ders programı.
3.  **Department**: `belongsTo`.

## Kritik Metodlar

*   [isAdmin()](./isAdmin.md): Kullanıcının yetki kontrolünü yapar.
*   [getLessons()](./getLessons.md): Hocaya atanmış dersleri getirir.
