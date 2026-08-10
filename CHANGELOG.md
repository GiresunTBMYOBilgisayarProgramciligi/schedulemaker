# Changelog

## [0.3.0] - 2026-08-10

### Added
- **Hoca İstekleri ve Program Notları Yönetim Sistemi**: Akademisyenlerin dönemsel kısıt ve notlarını profil sayfasından iletebilmesi, yetkililerin durum güncelleme ('Gereği Yapıldı', 'Reddedildi', 'Bilgi Verildi'), okundu takibi, silme yetkileri ve otomatik e-posta bilgilendirme sistemi eklendi (#75).
- **Liste Sayfaları Toplu İşlem (Bulk Actions) & Ders Birleştirme**: Liste sayfaları için toplu seçim, silme, pasifleştirme, bölüm/program kaskad güncellemesi ve mükerrer ders birleştirme mantığı entegre edildi.
- **Sağ-Tık Arayüz İşlemleri ve Derslik Çakışma Yönetimi**: Ders programı kartlarında sağ tık ile derslik düzenleme ve çakışma durumunda otomatik boş derslik değiştirme önerisi sunan modal eklendi (#14).
- **Birim Bazlı Kademeli Seçim Altyapısı**: Derslik, hoca ve takvim sayfalarında üst birim ve bölümlere göre dinamik kademeli filtreleme altyapısı eklendi.
- **Otomatik Rol Güncelleme**: Bir kullanıcıya bölüm başkanı atandığında kullanıcının rolünün otomatik güncellenmesi sağlandı.

### Changed
- **DataTables 3.0 & Vanilla JS Dönüşümü**: DataTables kütüphanesi v3.0 sürümüne yükseltildi ve jQuery bağımlılığı kaldırılarak Vanilla JS yapısına geçildi.
- **Arayüz ve Mobil Uyumluluk (Responsive)**: Mobil cihazlar için arayüz kullanımı, modal pencereleri, DataTables filtre ikon hizalamaları iyileştirildi; ders listelerinden Dönem/Yıl sütunları sadeleştirildi.
- **Standart Modal & Silme Mekanizması**: Silme ve onay süreçleri projedeki standart `Modal` sınıfı ve `ajaxFormDelete` yapısıyla yeniden yapılandırıldı.

### Fixed
- **Profil Sayfası ve Bağlı Dersler**: Profil sayfasındaki ders akordiyonunun akademik yıl ve döneme göre sıralanması sağlandı, bağlı (child) derslere popover bilgisi eklendi ve haftalık ders saati toplamından bağlı dersler hariç tutuldu.
- **Ders & Sınav Programı Düzeltmeleri**: Grup ders birleştirmelerindeki veri kayıpları, transaction rollback ve DTO pointer hataları ile sınav ögesi düzenleme/çoğaltma sorunları giderildi (#61).
- **404 Hata Yönetimi**: Tanımsız rotalar `NotFoundException` ile yakalanarak veritabanı hata logu kirliliği engellendi.
- **Repository ve Model Hata Düzeltmeleri**: `BaseRepository::find` metodunda null ID kontrolleri eklendi, atanmamış öğretim elemanı durumlarındaki null ID hataları ve pasif durumdaki checkbox kaydetme sorunları çözüldü.

## [0.2.9] - 2026-07-27

### Added
- **Ders Görevlendirmesi Mimarisi (LessonAssignment)**: Ders ve öğretim elemanı atamaları için dönemsel `LessonAssignment` mimarisine geçildi (#85).
- **Otomatik Hücre Birleştirme (Auto-Merge)**: Ders programında aynı derse ait bitişik saat dilimindeki öğeler için otomatik birleştirme ve detaylı loglama altyapısı eklendi.
- **Öğe Kilitleme**: Ders ve sınav programı öğeleri (slotları) için kilitleme (lock) özelliği eklendi.
- **Bina & Derslik Geliştirmeleri**: Bina listesinde bağlı birim adlarının gösterimi, derslik sayfasında bina ilişkisi ve kademeli seçim altyapısı eklendi.

### Changed
- **Listeler ve İkonlar**: Arayüz listelerinde görsel ikon düzenlemeleri ve iyileştirmeler yapıldı (#78).
- **Kod Mimarisi (Clean Code)**: Satır içi (inline) namespace kullanımı kaldırılarak PSR standartlarına uygun `use` bildirimlerine geçildi.

### Fixed
- **Sınav Programı Sürükle-Bırak & Model Düzeltmeleri**: Sınav programında sürükle-bırak taşıma, veritabanı sorgularındaki `semester` sütun hataları ve `Lesson::IsScheduleComplete()` metodundaki çakışmalar giderildi.
- **Program Dışı Takvimler ve Dışa Aktarım**: Program dışı takvimlerde `semester_no` kısıtlamaları kaldırıldı, veritabanı temizlendi ve dışa aktarım eşleşme hataları düzeltildi.
- **Yetkilendirme (Policy & Importer)**: `LessonPolicy::create`, `UserImporter` ve `LessonImporter` sınıflarındaki `Gate::check` yetki doğrulamaları ve kaskad izin kontrolleri düzeltildi.
- **Null-Safe Erişimler**: `stdClass` nesnelerinde öğretim elemanı (lecturer) erişimleri null-safe hale getirilerek tanımsız özellik (undefined property) hataları engellendi.

## [0.2.8] - 2026-07-23

### Added
- **Kaskad & Merkezi Yetkilendirme (Gate & Policy)**: Rol hiyerarşisi genişletildi; `Gate` ve `BasePolicy` ayrımı, `PermissionType` enum yapısı ve otomatik kaskad (hiyerarşik yukarı/aşağı yetki kontrolü) altyapısı entegre edildi (#80).
- **Bina & Birim İlişkisi**: Binaların birimlere (`unit_id`) bağlanması ve yetki mimarisi entegrasyonu sağlandı.
- **Dinamik AJAX Form Seçimleri**: Formlarda birim, bölüm ve program seçimleri için sıralı ve dinamik AJAX listeleme özelliği eklendi (#81).
- **Yetki Tabanlı Arayüz Elemanları**: Liste ve detay sayfalarındaki işlem butonları (Yeni Ekle, Sil vb.) ile sidebar menü öğeleri kullanıcının yetkilerine göre şartlı gösterilecek şekilde güncellendi.
- **Merkezi Hata Sayfası**: Merkezi yetkilendirme istisnaları (Authorization Exception) ve hata görünümleri için birleşik hata sayfası eklendi.

### Changed
- **Dinamik Veritabanı Filtreleme Mimarisi**: Controller katmanındaki manuel yetki filtrelemeleri temizlenerek `BaseRepository::getAuthorized()` metoduna taşındı; veri sorgularının dinamik yetki filtrelemesiyle çalışması sağlandı.
- **Dışa Aktarma (Export)**: Program ve veri dışa aktarma (export) süreçlerinde birim ve yetki entegrasyonu tamamlandı (#84).
- **İçe Aktarma (Import)**: Öğretim elemanı (Hoca) ve ders içe aktarma (Excel) işlemleri düzenlendi, süreçlere yetki kontrolleri dahil edildi (#83).
- **Arayüz ve Tema**: AdminLTE teması için açık/koyu mod seçeneği, ayarlar sayfası tasarımı yenilemesi ve sidebar menü sadeleştirmeleri yapıldı.
- **İlişkisel Mimari Temizliği**: Kullanılmayan `parent_lesson_id` sütunları kaldırılarak modeller arası ilişkisel yapıya geçildi.

### Fixed
- Birim silinirken bağlı bölümlerin pasif duruma getirilmesi ve uygun hata mesajının görüntülenmesi sağlandı.
- `SchedulePolicy` update metodunda oluşan `undefined property lesson_id` hatası çözüldü.
- Manager ve Submanager rollerinin birim kısıtlamaları (`unit_id`) ve `manage_*` yetki kontrolleri düzeltildi.
- Bölüm ekleme formlarında TomSelect sıfırlanma ve doğrulama (validation) kuralları hataları giderildi.
- Derslik ve ders programı düzenleme sayfalarında `AvailabilityService` entegrasyonu yapılarak yalnızca yetkili olunan derslerin listelenmesi sağlandı.
- Policy sınıflarında nullable User kabul eden durumlarda konuk (guest) erişimine izin verecek Gate kontrolü düzeltildi.

## [0.2.7] - 2026-07-16

### Added
- Şifre sıfırlama (Forgot Password) sistemi (Service, Repository, Mailer, Controller, View, DTO) eklendi.
- E-posta işlemleri için `Mailer` çekirdek sınıfı ve `Events` yapısı (Dispatcher, Listeners) oluşturuldu.
- `Settings` (Ayarlar) sayfasına "Mail Ayarları" sekmesi eklendi ve veritabanı ayarları ile entegre edildi.
- `lesson_combinations` tablosu oluşturularak ders ve sınav birleştirmeleri yeni tabloya taşındı.

### Changed
- `UserService` güncellenerek yeni kullanıcı oluşturma işleminde varsayılan "123456" şifresi yerine rastgele güçlü şifre ataması yapıldı.
- Profil güncellemelerinde yetki kontrolü sıkılaştırıldı; Bölüm, Program ve Unvan alanları yalnızca yöneticiler tarafından değiştirilebilir hale getirildi.
- `AjaxRouter` ve yetkilendirme (Auth) denetleyicileri (Controller) iyileştirildi; metotlar merkezi `sendResponse()` mimarisi ile uyumlu olarak `array` döndürecek şekilde refactor edildi.

### Fixed
- Ders programında eksik görünen derslerin listelenmemesi sorunu (AvailabilityService) giderildi.
- Sınav/ders atamalarında aynı saatte aynı dersliğe birden fazla grubun atanmasına neden olan çakışma (conflict) engellendi.
- Ders programı item'larının çoğalması (duplication) hatası çözüldü.
- Belirli durumlarda derslik (slot) silinmesini engelleyen problemler giderildi.
- Uygulama çekirdeğindeki (Router/Application) parametreli (Query string içeren) URL'lerin boş sayfa açmasına neden olan `ParseURL` mantık hatası düzeltildi.
- Rota (route) bulunamadığında uygulamanın beyaz sayfa döndürmesi yerine Exception fırlatması sağlandı.

### Security
- Uygulamadaki varsayılan ve güvensiz olan tüm "123456" şifreleri (admin hariç) iptal edilerek rastgele, bilinmeyen güçlü şifrelerle değiştirildi.

## [0.2.6] - 2026-07-14

### Added
- Yetkilendirme işlemleri için Middleware (`AuthMiddleware`, `GuestMiddleware`) katmanı eklendi.
- Route ve Action koruması için `#[AuthRequired]` ve `#[PublicAction]` attribute'ları eklendi.
- Veri transferi ve doğrulaması için DTO ve Validator katmanları eklendi.
- İş mantığını Controller'dan ayırmak için Service katmanı eklendi.
- Veritabanı işlemleri için Repository katmanı eklendi.
- `UserRole`, `UserTitle` ve `ClassroomType` için Enum yapıları oluşturuldu.

### Changed
- Proje kod mimarisi Clean Architecture/MVC standartlarına (Router -> Middleware -> Controller -> Validator -> DTO -> Service -> Repository -> Model) uygun olarak yeniden yapılandırıldı.
- `User`, `Department` ve `Classroom` modülleri yeni mimariye uygun olarak tamamen refactor edildi.
- Route yapılarındaki spagetti kodlar temizlenerek sadece yönlendirme yapacak şekilde sadeleştirildi.
- Dinamik yetki kontrolleri (Gate) yeni sisteme entegre edildi.
- Model sınıflarındaki `beforeDelete` gibi bağımlılıklar kaldırılarak Service katmanına taşındı.

## [0.2.5] - 2026-06-25

### Added
- Sınav çıktısına tarihler eklendi.
- Sınav programında derslik çıktısında gözetmen isimlerinin yazılması düzenlendi.
- Derslik ve gözetmen bilgisi ayrı sütuna değil tek ders bilgisi ile aynı sütuna yazılacak.
- Ders programında peş peşe olan (tek item) derslerin hücreleri birleştirildi.
- Sınav programında peş peşe olan hücrelerin slotları birleştirildi.
- Sınav atamasında gözetmen seçime tom-select eklendi, arama yapılabiliyor.
- Sınav programında da bağlı dersler gözükecek.
- Bağlı derslerin ders sayfasında gösterimi düzenlendi.
- Sınav programında bağımsız sınav birleştirme (exam_parent_lesson_id) yapısı uygulandı.
- Derslik sayfasına sınav programı eklendi.

### Fixed
- Final programında ders ekleme işlemi sonrasında hafta karışıklığı düzeltildi.
- Sınav başlangıç tarihi hatası düzeltildi.
- Bölüm başkanı olmayan bölümlerde hata vermesi düzenlendi.
- Sınav programında ders mevcudu hesaplaması düzenlendi.
- Program dışa aktarmada id parametresindeki array-int uyumsuzluk hatası (`find()` vs `where()`) düzeltildi.

### Changed
- Excel ve HTML için ayrı satır hazırlama (rows) işlemleri birleştirilerek kod temizliği yapıldı.
- Sınav programında sınıf/gözetmen sütunu kaldırıldı.
- Sınav programı çıktısında hoca ve gözetmen isimleri gösterimi düzenlendi (isimler alt alta yazılacak).
- `ImportExportManager.php` silinerek daha düzenli ve yönetilebilir bir yapıya çevrildi.
- Fazla/kullanılmayan parametreler kaldırıldı.
- Frontend sınav dışa aktarma işlemleri için hazırlandı.
- Program dışa aktarma sayfası düzenlendi.
- npm update gerçekleştirildi.
