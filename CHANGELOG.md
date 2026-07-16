# Changelog

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
