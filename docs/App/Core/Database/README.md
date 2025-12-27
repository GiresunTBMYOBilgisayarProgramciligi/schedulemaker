[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Database**

---
## ER Diyagramı

```mermaid
erDiagram
    USERS ||--o{ LOGS : "logs"
    DEPARTMENTS ||--o{ USERS : "members"
    PROGRAMS ||--o{ USERS : "members"
    CHAIRPERSON ||--|| USERS : "leads department"
    DEPARTMENTS ||--o{ PROGRAMS : "contains"
    PROGRAMS ||--o{ LESSONS : "has"
    USERS ||--o{ LESSONS : "teaches"
    DEPARTMENTS ||--o{ LESSONS : "belongs to"
    SCHEDULES ||--o{ SCHEDULE_ITEMS : "contains"
    CLASSROOMS ||--o{ SCHEDULE_ITEMS : "reserved in (data)"
    LESSONS ||--o{ SCHEDULE_ITEMS : "scheduled in (data)"
```

## İlişki Haritası (Foreign Keys)

1.  **`schedule_items.schedule_id`** -> `schedules.id` (`ON DELETE CASCADE`)
2.  **`departments.chairperson_id`** -> `users.id`
3.  **`programs.department_id`** -> `departments.id`
4.  **`lessons.lecturer_id`** -> `users.id`

*(Tam liste ve tablo detayları için Model dokümanlarına bakınız.)*

## Metod Listesi

*   [getConnection()](./getConnection.md): Veritabanı bağlantı örneğini döndürür (Singleton).
