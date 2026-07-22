# Sistem Yetki ve Rol Dokümantasyonu

Bu doküman, sistemdeki kullanıcı rollerini, aralarındaki hiyerarşiyi, varsayılan politika (`Policy`) sınıfı kurallarını ve JSON tabanlı özel yetkilendirme (`Gate`) mimarisini açıklamaktadır.

## 1. Rol Hiyerarşisi

Sistemde bulunan roller, yetki seviyelerine göre yukarıdan aşağıya (100 -> 50) sıralanmıştır. Daha yüksek seviyeye sahip roller, alt rollerin yetki gerektiren varsayılan (genel) sayfalarına `Gate::allowsRole` metodu kullanıldığında erişebilir.

| Rol Key | Seviye | Açıklama |
| :--- | :--- | :--- |
| `admin` | 100 | Sistem Yöneticisi. BasePolicy aracılığıyla sistemdeki tüm kontrollere istisnasız (`true`) erişimi vardır. |
| `manager` | 90 | Müdür. Okul/Fakülte geneli işlemler (tüm bölümler, binalar, derslikler). |
| `submanager` | 80 | Müdür Yardımcısı. Müdür ile benzer erişim haklarına sahiptir. |
| `secretary` | 75 | Sekreter. İdari birim işleri (özellikle Bina/Derslik işlemleri) ve tanımlı özel görevler. |
| `department_head` | 70 | Bölüm Başkanı. Sadece kendi bölümü ve altındaki programlara erişimi vardır. |
| `research_assistant` | 65 | Araştırma Görevlisi. Varsayılan olarak kısıtlıdır, ancak **bir derse hoca olarak atanırsa aynı `lecturer` (öğretim elemanı) gibi kendi dersini görebilir ve düzenleyebilir.** Ayrıca özel yetkilerle (JSON) donatılabilir. |
| `lecturer` | 60 | Öğretim Elemanı (Hoca). Sadece kendi derslerini görebilir/düzenleyebilir. |
| `user` | 50 | Standart Kullanıcı / Öğrenci. |

## 2. Temel Yetki Dağılımı (Policy Kuralları)

*Genel Kural:* `admin` rolüne sahip kullanıcılar `BasePolicy::before()` metodu sayesinde aşağıdaki tüm işlemleri sınırsız yapabilir. Diğer roller için geçerli kurallar aşağıda detaylandırılmıştır.

### Birim (Fakülte/Okul) İşlemleri (`UnitPolicy`)
*   **Listeleme:** `manager` ve `submanager` (Sistemdeki birimleri listeleyebilir).
*   **Görme (Detay):** Kullanıcının kendi birimi ise görebilir. Kendi birimindekileri yöneten `manager`, `submanager` veya ilgili birimde kaskad (kapsayıcı) olarak `MANAGE_UNIT` yetkisine sahip kullanıcılar.
*   **Ekleme:** `admin` yapabilir. Ancak `MANAGE_UNIT` yetkisine sahip kullanıcılar da altındaki hiyerarşide ekleme haklarına zımni olarak sahip olur.
*   **Düzenleme:** Kendi birimi için `manager` ve `submanager`. Ek olarak ilgili birim için `MANAGE_UNIT` kaskad özel yetkisine sahip kullanıcılar.
*   **Silme:** Sadece `admin` ve `MANAGE_UNIT` kaskad özel yetkisine sahip kullanıcılar birim silebilir. (`manager` ve `submanager` varsayılan olarak birim silemez).

### Bölüm İşlemleri (`DepartmentPolicy`)
*   **Listeleme:** `manager`, `submanager`, `department_head`.
*   **Görme:** Kendi birimindeki bölümleri `manager`, `submanager` görebilir. Ayrıca kullanıcı kendi bölümünü görebilir. `MANAGE_DEPARTMENT` (veya üstü olan `MANAGE_UNIT`) kaskad özel yetkisine sahip olanlar da görüntüleyebilir.
*   **Ekleme:** `manager` ve `submanager` kendi birimine bölüm ekleyebilir. Ayrıca `MANAGE_DEPARTMENT` veya `MANAGE_UNIT` yetkisine sahip olanlar kendi yetki alanları içerisine ekleme yapabilir.
*   **Düzenleme / Silme:** Kendi birimindeki bölümleri `manager` ve `submanager` güncelleyip silebilir. Ayrıca `MANAGE_DEPARTMENT` veya `MANAGE_UNIT` yetkilileri yetkili oldukları varlıkları silebilir.

### Program İşlemleri (`ProgramPolicy`)
*   **Listeleme:** `manager`, `submanager`, `department_head`.
*   **Görme:** Kendi birimindeki programları `manager` ve `submanager`, kendi bölümüne ait programları ise `department_head` görebilir. Akademisyen veya öğrenciler kayıtlı oldukları programı görebilir. Ek olarak `MANAGE_PROGRAM` (veya `MANAGE_DEPARTMENT` / `MANAGE_UNIT`) özel yetkisine sahip olanlar.
*   **Ekleme:** Genel olarak `manager` ve `submanager`. Ayrıca `MANAGE_DEPARTMENT` yetkilileri kendi bölümlerine ait program ekleyebilir (Üst yetkiler alt yetkileri kapsar).
*   **Düzenleme:** Kendi birimindeki programları `manager` ve `submanager`, kendi bölümünün programlarını `department_head`. Ayrıca `MANAGE_PROGRAM` veya üst yetkiye sahip olanlar.
*   **Silme:** Kendi birimindeki programları `manager` ve `submanager` silebilir. `MANAGE_DEPARTMENT` ilgili departmandaki tüm programları silebilirken, `MANAGE_PROGRAM` sadece kendi yetkisindeki programı silebilir. (Bölüm başkanının doğrudan program silme yetkisi yoktur).

### Bina ve Derslik İşlemleri (`BuildingPolicy`, `ClassroomPolicy`)
*   **Listeleme:** Rolü `secretary` veya daha üstü olanlar (`admin`, `manager`, `submanager`) ile `MANAGE_BUILDINGS` yetkisine sahip kullanıcılar.
*   **Görme, Ekleme, Düzenleme, Silme:** Sadece kendi birimine (unit_id) ait olanlar üzerinde rolü `secretary` ve daha üstü olan kullanıcılar işlem yapabilir. Veya `MANAGE_BUILDINGS` (veya üst) yetkisi verilen bina ve derslikler üzerinde işlem yapılabilir.

### Ders İşlemleri (`LessonPolicy`)
*   **Listeleme:** `manager`, `submanager`, `department_head` veya genel olarak herhangi bir alanda `MANAGE_LESSONS` (veya üst) yetkisi bulunanlar.
*   **Görme:** Kendi birimindeki dersleri `manager`, `submanager`, kendi bölümünün derslerini `department_head` görebilir. Dersi veren hoca (araştırma görevlisi olsa dahi `lecturer_id` kendisi ise) kendi dersini görebilir. Ayrıca `MANAGE_LESSONS` / `MANAGE_SCHEDULE` yetkilileri.
*   **Ekleme:** Kendi birimine `manager` ve `submanager`, kendi bölümüne `department_head`. Ayrıca `MANAGE_LESSONS` / `MANAGE_SCHEDULE` (veya daha üst) kaskad yetkilileri.
*   **Düzenleme:** Kendi birimine `manager` ve `submanager`, kendi bölümüne `department_head`. Dersin hocası kendi dersini güncelleyebilir. Ayrıca `MANAGE_LESSONS` / `MANAGE_SCHEDULE` (veya daha üst) özel yetkilileri.
*   **Silme:** Kendi biriminde `manager` ve `submanager`, kendi bölümünde `department_head` ile `MANAGE_LESSONS` / `MANAGE_SCHEDULE` (veya daha üst) özel yetkilileri.
*   **Ders Birleştirme (Combine):** Sadece `admin`, `manager`, `submanager` ve `MANAGE_LESSONS` / `MANAGE_SCHEDULE` özel yetkisine sahip olan kullanıcılar.

### Kullanıcı İşlemleri (`UserPolicy`)
*   **Listeleme:** `manager`, `submanager`, `department_head`.
*   **Görme:** `manager` ve `submanager` kendi birimindekileri ve birimsiz kullanıcıları görebilir. `department_head` kendi bölümündeki kullanıcıları veya bölümsüzleri görebilir. Her kullanıcı kendi profilini görebilir. `MANAGE_UNIT` veya `MANAGE_USERS` yetkilileri kendi alanlarındakini görebilir.
*   **Ekleme:** Kendi birimine (veya birimsiz) `manager`, `submanager`; kendi bölümüne `department_head`; ilgili `MANAGE_USERS` yetkilileri.
*   **Düzenleme / Silme:** Kendi birimindeki kullanıcılar için `manager`, `submanager`; kendi bölümündeki kullanıcılar için `department_head`. Her kullanıcı kendi profilini sadece *güncelleyebilir* (silemez). `MANAGE_UNIT` veya `MANAGE_USERS` yetkilileri.

### Takvim İşlemleri (`SchedulePolicy`)
*   **Listeleme:** Sadece üst yönetim (`manager`, `submanager`, `admin`).
*   **Görme:** Herkes (Home sayfasında genel programlara açık erişim sağlanmıştır).
*   **Ekleme, Güncelleme ve Silme:** 
    *   **Genel Yetkilendirme:** `manager` ve `submanager` kendi birimi ve o birimin altındaki programları düzenleyebilir. `department_head` kendi bölüm ve programını düzenleyebilir. `MANAGE_SCHEDULE` yetkilisi, yetkisinin tanımlandığı ilgili birimi/bölümü/programı ve altındaki tüm birimleri düzenleyebilir.
    *   **Program Takvimi:** Programın bağlı olduğu bölüm başkanı ve `MANAGE_SCHEDULE` yetkisine sahip kullanıcılar.
    *   **Kullanıcı Takvimi:** Bölümsüz hocalar kendi programını, hoca kendi programını, hocanın bölüm başkanı ve `MANAGE_SCHEDULE` yetkilileri.
    *   **Ders Takvimi:** Dersin bağlı olduğu bölümün başkanı ve `MANAGE_SCHEDULE` yetkilileri.
    *   **Sınıf Takvimi Durumu:** Sınıf takvimlerine (Classroom) müdahale edilmesi noktasında katı bir Gate kısıtlaması uygulanmamıştır (`return true`). Bu nedenle takvim yerleştirme işlemleri esnektir ve çakışmalar sistemin diğer uyarı/doğrulama (planlama ekranındaki filtreleme ve denetim mekanizmaları) adımları ile yönetilir.

### Yönetim ve Ayarlar Sayfaları
*   **Ayarlar Sayfası (`SettingPolicy`):** Sadece `admin` rolündeki kullanıcılar sistemi genel olarak etkileyen ayarlar sayfasını görüntüleyebilir ve düzenleyebilir.
*   **Log Sayfası (`AdminPageController`):** Sistem kayıtlarını (Logs) sadece `admin` rolü görebilir.
*   **Yetki Düzenleme Sayfası (`PermissionController`):** `manager` ve `submanager` kendi birimleri ile altındaki bölüm ve programlar için yetki (permissions) düzenlemesi yapabilir.

## 3. JSON Tabanlı Özel (Granüler) Yetkilendirme

Varsayılan rol hiyerarşisinin yetmediği durumlarda (Örneğin bir Araştırma Görevlisine sadece belirli bir programın takvimini düzenleme yetkisi verilmek istendiğinde), Moodle mantığına benzer JSON tabanlı kaskad özel yetkilendirme kullanılır. Bu sistem `BasePolicy::hasCascadePermission()` metodu üzerinden çalışır.

Özel yetkiler, veritabanında `settings` tablosunda **`user_{id}`** anahtarı (key) ve `group = permissions` altında JSON formatında tutulur.

### Alt Sınıflara (Aşağı Yönlü) Yetki Mirası
`BasePolicy.php` içerisindeki kaskad kontrol mekanizması ile **üst yetkiye sahip bir kullanıcı, altındaki varlıklarda zımni olarak (implicit) yetkili sayılır.**
Örneğin `manage_department` yetkisine sahip bir kullanıcı otomatik olarak o bölümün `manage_program`, `manage_users`, `manage_schedule` ve `manage_lessons` yetkilerine de sahiptir. `manage_unit` yetkisi tüm bunları kapsar.

### Özel Yetki JSON Yapısı (Örnek):
```json
{
  "units": {
    "1": ["view", "update"]
  },
  "departments": {
    "3": ["view", "manage_users", "manage_schedule", "update"]
  },
  "programs": {
    "5": ["view", "manage_schedule"]
  }
}
```

### Özel Yetki Tipleri (Actions)
*   `view` : Belirtilen varlığı görebilme.
*   `update` : Belirtilen varlığın temel bilgilerini güncelleyebilme.
*   `delete` : Belirtilen varlığı silebilme.
*   `manage_users` : Belirtilen bölüm/program altına yeni kullanıcı ekleme, mevcut kullanıcıları güncelleme ve silme.
*   `manage_schedule` : Belirtilen bölüm/program için takvime ders yerleştirme işlemlerini yönetme.
*   `manage_lessons` : Belirtilen bölüm/program için ders tanımlama, güncelleme ve silme.
*   `manage_buildings` : Bina listesi ile binaya ait derslikleri düzenleme (Genelde binalara veya birim geneline özel verilir).
*   `manage_unit` : İlgili Birimi (Fakülte/Okul vb.) yönetme.
*   `manage_department` : İlgili Bölümü yönetme. (Alt sınıflara etki eder).
*   `manage_program` : İlgili Programı yönetme. (Alt sınıflara etki eder).

### Mimari Yapı (Gate & BasePolicy Ayrımı)

- **`Gate.php` (Dispatcher):** Yalnızca model ile Policy sınıfını eşleştiren ve ilgili politika metodunu (`$policy->$action(...)`) tetikleyen yalın bir yönlendiricidir. İçerisinde rol veya iş kuralı kararları barındırmaz.
- **`BasePolicy.php` (Base Class):** Tüm politika sınıflarının atasıdır. Global `admin` kontrolünü (`before()`) ve kaskad JSON yetki kontrolünü (`$this->hasCascadePermission(...)`) sağlar. Ayrıca tanımlanmamış `manage_*` aksiyonlarını `__call` sihirli metodu ile otomatik olarak kaskad kontrolüne yönlendirir.
- **Politika Sınıfları (Örn: `DepartmentPolicy.php`, `ProgramPolicy.php`):** Her modelin rol bazlı iş kurallarını (örn. `department_head` veya `manager` rolleri için `manage_schedule`, `manage_lessons` vb.) açık metodlar halinde tanımlar. Rol yetkisi yoksa `$this->hasCascadePermission` ile kaskad JSON yetkilerine bakar.