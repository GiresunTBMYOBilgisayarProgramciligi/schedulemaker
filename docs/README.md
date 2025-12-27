# Schedule Maker Dokümantasyonu

Schedule Maker projesinin teknik dokümantasyonuna hoş geldiniz. Bu dokümantasyon, projenin mimarisini, veritabanı yapısını, backend ve frontend mantığını detaylandırmak amacıyla oluşturulmuştur.

## İçindekiler

1.  **[Mimari Genel Bakış](./architecture/overview.md)**
    *   Projenin temel MVC yapısı, Core bileşenler ve Router mekanizması.
    *   Sistem nasıl çalışır?

2.  **[Veritabanı Yapısı](./architecture/database.md)**
    *   Veritabanı şeması, tablolar (`schedule_items`, `lessons` vb.) ve ilişkiler.
    *   Hangi veri neyi temsil eder?

3.  **[Backend Mantığı](./backend/scheduling-logic.md)**
    *   Ders programı oluşturma algoritmaları.
    *   Çakışma kontrolü (Conflict Resolution).
    *   Status (Single, Group, Preferred) mantığı.

4.  **[API ve Router](./backend/api.md)**
    *   Ajax uç noktaları ve istek/yanıt yapıları.
    *   `AjaxRouter` ve `ScheduleController` etkileşimi.

5.  **[Frontend Etkileşimleri](./frontend/interactions.md)**
    *   Sürükle-Bırak (Drag & Drop) mantığı.
    *   `ScheduleCard.js` detayları.
    *   Kullanıcı arayüzü ve UX kuralları.

---

Bu dokümantasyon "Docs as Code" prensibiyle hazırlanmıştır ve kod tabanıyla birlikte güncel tutulması hedeflenmektedir.
