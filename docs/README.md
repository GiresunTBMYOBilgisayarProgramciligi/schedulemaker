# Giresun TBMYO Ders Programı Sistemi - Teknik Dokümantasyon

Bu dokümantasyon, projenin hem backend hem frontend mimarisini, klasör yapısını aynalayarak detaylı bir şekilde açıklar.

## 📂 Proje Yapısı ve Dokümanlar

### 🛡️ App (Backend)
*   **[Core](./App/Core/README.md)**: Uygulama çekirdeği.
    *   [Application](./App/Core/Application/README.md)
    *   [Database](./App/Core/Database/README.md)
    *   [Model](./App/Core/Model/README.md)
    *   [Router](./App/Core/Router/README.md)
    *   [AssetManager](./App/Core/AssetManager/README.md)
    *   [ImportExportManager](./App/Core/ImportExportManager/README.md)
    *   [ErrorHandler](./App/Core/ErrorHandler/README.md)
    *   [Log & Logger](./App/Core/Log/README.md)
    *   [View Engine](./App/Core/View/README.md)
*   **[Controllers](./App/Controllers/README.md)**: İş mantığının yönetildiği kontrolcüler.
    *   [ScheduleController](./App/Controllers/ScheduleController/README.md)
    *   [UserController](./App/Controllers/UserController/README.md)
    *   [LessonController](./App/Controllers/LessonController/README.md)
    *   [ClassroomController](./App/Controllers/ClassroomController/README.md)
    *   [DepartmentController](./App/Controllers/DepartmentController/README.md)
    *   [ProgramController](./App/Controllers/ProgramController/README.md)
    *   [SettingsController](./App/Controllers/SettingsController/README.md)
*   **[Models](./App/Models/README.md)**: Veritabanı modelleri.
    *   [User](./App/Models/User/README.md)
    *   [Lesson](./App/Models/Lesson/README.md)
    *   [ScheduleItem](./App/Models/ScheduleItem/README.md)
    *   [Classroom & Department](./App/Models/Classroom/README.md)
    *   [Program & Schedule](./App/Models/Program/README.md)
*   **[Routers](./App/Routers/README.md)**: URL yönlendirmeleri.
    *   [Router Yapılandırmaları](./App/Routers/SpecificRouters.md)
*   **[Helpers](./App/Helpers/README.md)**: Global yardımcı fonksiyonlar.

### 🎨 Assets & Frontend
*   **[JavaScript Dosyaları](./Public/assets/js/README.md)**
*   **[Stil Dosyaları (CSS)](./Public/assets/css/README.md)**
*   **[İndirilebilir Şablonlar](./Public/assets/downloads/README.md)**
*   **[Node Modülleri](./Public/assets/node_modules.md)**
*   **[Genel Asset Özeti](./Public/assets/README.md)**

### 🏗️ Genel Mimari
*   **[Veritabanı Şeması](./architecture/database.md)**: Tablolar ve ER Diyagramı.
*   **[Genel Bakış](./architecture/overview.md)**: MVC yapısı ve Request Lifecycle.

---
> [!NOTE]
> Her sınıfa ait kendi klasörü içinde her metodun ayrı `.md` dosyası bulunmaktadır.
