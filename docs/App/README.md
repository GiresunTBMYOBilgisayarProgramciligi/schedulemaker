[🏠 Ana Sayfa](../README.md) / **App**

---
# Backend Uygulama Katmanı

Bu dizindeki alt bileşenler ve görevleri:

*   **[Core](./Core/README.md)**: Framework'ün motoru (Router, Database, Application vb.)
*   **[Routers](./Routers/README.md)**: Sadece URL karşılayan ve Middleware tetikleyen rotalar.
*   **Middlewares**: Kapı Güvenliği & Filtreler (Auth, Guest, RoleCheck).
*   **Validators**: Request doğrulamaları (ScheduleItemValidator vb.).
*   **DTOs**: Katmanlar arası taşınan saf veri paketleri (Data Transfer Objects).
*   **[Controllers](./Controllers/README.md)**: Rota ile Servis arasında köprü, HTTP/JSON yanıt yöneticileri.
*   **[Services](./Services/README.md)**: UYGULAMANIN BEYNİ (İş Mantığı, Çakışma Analizleri vb.)
    *   *Export*: Excel, PDF, Ics dışa aktarma servisleri.
    *   *Import*: Excel'den içe aktarma servisleri.
*   **Repositories**: Veritabanı sorgu merkezi (SQL/ORM sarmalayıcıları).
*   **[Models](./Models/README.md)**: Veritabanı tablo şablonları / ORM sınıfları.
*   **Policies**: Modele özel yetki kuralları (DepartmentPolicy, UserPolicy).
*   **Events**: Tetiklenen olaylar (UserRegistered, ScheduleUpdated).
*   **Listeners**: Olayları dinleyen yan işler (SendWelcomeEmail, LogActivity).
*   **Mailers**: E-posta motoru ve SMTP yapılandırmaları.
*   **Notifications**: Sistem içi veya dışı bildirim kanalları.
*   **Exceptions**: Projeye özel hata sınıfları (ScheduleConflictException).
*   **[Helpers](./Helpers/README.md)**: Bağımsız yardımcı fonksiyonlar (TimeHelper, Formatters).
*   **Views**: Sadece ekrana veri basan şablonlar (HTML).

## Katmanlar Arası Veri Akış Şeması (Data Flow)

Modern MVC ve katmanlı mimaride (Clean Architecture) bir isteğin yaşam döngüsü aşağıdaki sırayı izler:

```text
Tarayıcı (İstek / HTTP Request) 
  └── Rota (Routers) -> (İsteği karşılar ve Middleware'leri tetikler)
        └── Filtre (Middlewares) -> (Kimlik & Genel Rol Kontrolü - Örn: Giriş yapmış mı?)
              └── Yönetici (Controllers) -> (Trafiği yönetir, HTTP Yanıtını döner)
                    ├── Doğrulama (Validators) -> (Gelen veri yapısal olarak doğru mu?)
                    ├── Paketleme (DTO) -> (Doğrulanmış ham veri, güvenli DTO nesnesine dönüşür)
                    └── İş Mantığı (Services) -> (DTO'yu alır, algoritmaları çalıştırır, Event tetikler)
                          ├── Depo (Repositories) -> (Veritabanından Modeli çeker veya kaydeder)
                          └── Yetki (Policies) -> (Çekilen modele işlem yapma hakkı var mı?)
```
