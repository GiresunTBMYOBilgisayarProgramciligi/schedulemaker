# Sistem Yetki ve Rol Dokümantasyonu

Bu doküman, sistemdeki kullanıcı rollerini, aralarındaki hiyerarşiyi ve JSON tabanlı özel yetkilendirme mimarisini açıklamaktadır.

## 1. Rol Hiyerarşisi

Sistemde bulunan roller, yetki seviyelerine göre yukarıdan aşağıya (100 -> 50) sıralanmıştır. Daha yüksek seviyeye sahip roller, alt rollerin yetki gerektiren varsayılan (genel) sayfalarına erişebilir.

| Rol Key | Seviye | Açıklama |
| :--- | :--- | :--- |
| `admin` | 100 | Sistem Yöneticisi. Tüm sisteme tam erişimi vardır. |
| `manager` | 90 | Müdür. Okul/Fakülte geneli işlemler (tüm bölümler, binalar, derslikler). |
| `submanager` | 80 | Müdür Yardımcısı. Müdür ile benzer erişim haklarına sahiptir. |
| `secretary` | 75 | Sekreter. İdari birim işleri ve tanımlı özel görevler. |
| `department_head` | 70 | Bölüm Başkanı. Sadece kendi bölümü ve altındaki programlara erişimi vardır. |
| `research_assistant` | 65 | Araştırma Görevlisi. Varsayılan olarak kısıtlıdır, özel yetkilerle donatılabilir. |
| `lecturer` | 60 | Öğretim Elemanı (Hoca). Sadece kendi derslerini görebilir/düzenleyebilir. |
| `user` | 50 | Standart Kullanıcı / Öğrenci. |

## 2. Temel Yetki Dağılımı

### Bina ve Derslik İşlemleri
Binalar ve derslikler, doğrudan bir bölüme bağlı olmadıkları için genel (ortak) varlıklardır.
*   **Görme, Ekleme, Düzenleme, Silme:** Yalnızca `admin`, `manager` ve `submanager` tarafından yapılabilir.

### Birim (Fakülte/Okul) İşlemleri
*   **Listeleme ve Görme:** Sadece üst yönetim.
*   **Ekleme, Düzenleme, Silme:** Yalnızca `admin` ve `manager` düzeyindeki roller yapabilir. Özel olarak `update` veya `delete` yetkisi tanımlananlar da eylem gerçekleştirebilir.

### Bölüm ve Program İşlemleri
*   **Görme:** Üst yönetim ve ilgili bölümün/programın başkanı (`department_head`). Kayıtlı kullanıcılar sadece kendi bölümünü/programını görebilir.
*   **Ekleme:** Sadece üst yönetim.
*   **Düzenleme:** Üst yönetim ve ilgili bölüm başkanı.

### Ders İşlemleri
*   **Görme:** Üst yönetim, ilgili bölümün başkanı ve dersin hocası.
*   **Ekleme:** Üst yönetim ve ilgili bölüm başkanı kendi bölümüne ekleyebilir.
*   **Düzenleme:** Üst yönetim, bölüm başkanı (kendi bölümü) ve dersin hocası.
*   **Silme:** Üst yönetim ve bölüm başkanı (kendi bölümü).

### Kullanıcı İşlemleri
*   **Görme:** Üst yönetim tümünü; bölüm başkanı kendi bölümündekileri görebilir. Kullanıcı sadece kendi profilini görebilir.
*   **Ekleme/Düzenleme:** Üst yönetim ve bölüm başkanı (sadece kendi bölümüne).

### Takvim İşlemleri
*   Sınıf programları, hoca programları ve bölüm/program takvimleri, takvimin sahibine (owner) göre kontrol edilir. Bölüm başkanı kendi altındaki takvimlere tam müdahale edebilir. Öğretim görevlisi kendi takvimine müdahale edebilir.

## 3. JSON Tabanlı Özel (Granüler) Yetkilendirme

Varsayılan rol hiyerarşisinin yetmediği durumlarda (Örneğin bir Araştırma Görevlisine sadece belirli bir programın takvimini düzenleme yetkisi verilmek istendiğinde), Moodle mantığına benzer JSON tabanlı özel yetkilendirme kullanılır.

Özel yetkiler, veritabanında `settings` tablosunda `user_{id}_permissions` anahtarı (key) altında JSON formatında tutulur.

### Özel Yetki JSON Yapısı:
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
*   `manage_buildings` : Bina listesi ile binaya ait derslikleri düzenleme (Genelde binalara özel verilir).
*   `manage_unit` : İlgili Birimi (Fakülte/Okul vb.) yönetme.
*   `manage_department` : İlgili Bölümü yönetme.
*   `manage_program` : İlgili Programı yönetme.

*Kullanıcı Policy sınıfları (Örn: `DepartmentPolicy.php`), herhangi bir eyleme karar verirken önce rol tabanlı varsayılan hiyerarşiyi kontrol eder. Oradan yetki alamazsa, kişinin `Gate::getUserPermissions($user->id)` ile gelen JSON verisinde ilgili varlık ve `action` (eylem) eşleşmesi olup olmadığını kontrol eder.*