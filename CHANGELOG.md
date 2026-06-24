# Changelog

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
