# Changelog

## [0.3.5] - 2026-10-27

> **Veritabanı güncellemesi gerekli:** Mevcut kurulumlarda `update-database.sql` çalıştırılarak kullanılmayan `logs` tablosu kaldırılmalıdır.

### Added
- **Birleşik Hoca Sınav Programı**:
  - Hoca profilinde ve herkese açık hoca sayfasında sınav programı; hocanın o dönem verdiği derslerin sınavları ve gözetmenlik görevleriyle birlikte tek görünümde gösterilir (`ExamScheduleService::getLecturerExamSchedule`). Birleştirme okuma anında yapılır, veritabanına yeni kayıt yazılmaz.
  - Sınav birleştirmesindeki child dersler parent kaydında "Sınav Bağlı Dersler" olarak, aynı slottaki grup kardeşleri tek kartta "Grup A, B" rozetiyle gösterilir; hocanın kendi dersindeki gözetmenlik kaydı mükerrer gösterilmez.
  - Gözetmenlik kartlarında "Gözetmenlik" rozeti, sınavı yapılan dersin hocası ve sınav salonu ayrı satırlarda yer alır; sınav tablosu aynı hücrede birden çok kaydı destekler.
  - Izgara Excel, liste Excel, ICS ve JSON dışa aktarımları da aynı birleşik görünümü kullanır (`ExamScheduleService::findScheduleWithItems`).
- **Günlük En Fazla 2 Sınav Uyarısı**:
  - Sınav ekleme, taşıma ve düzenlemede aynı program/yarıyıl için bir günde 2'den fazla sınav oluşuyorsa yönetmelik uyarısı gösterilir; kullanıcı onaylarsa işlem devam eder. Aynı saat dilimindeki sınavlar (grup, sınav birleştirme) tek sayılır.
- **Yönetici Paneli Gözetmenlik İstatistikleri**:
  - Hocaların sınav türüne göre gözetmenlik görevi sayısı ve toplam süresini gösteren dashboard kartı (özet, yığılmış çubuk grafik ve liste); yıl, dönem, birim ve bölüm filtresi (`UserRepository::getExamDutyStats`).
- **Gözetmen Seçimi İyileştirmeleri**:
  - Uygun gözetmen listesi programın/hocanın/dersin bağlı olduğu birimdeki hocalar ve o birimde ders veren hocalarla sınırlandırıldı.
  - Sınav gününde dersi veya sınavı olan gözetmenler listenin başına alınır ve adlarının yanında "Çarşamba günü dersi var" gibi bilgi notu gösterilir.
- **Excel Liste Çıktısı Seçenekleri**:
  - Liste çıktısında modal seçenekleri (ders kodu, hoca, program, gözetmen, staj) dikkate alınır; sınav listesinde Hoca ve Gözetmen ayrı sütunlardır.
  - Sınav başlangıç tarihi tanımlıysa "Gün" yerine sıralanabilir gerçek tarih yazılır, satırlar tarih/saat sırasıyla dizilir.
  - Derslik sınav listesi için "Derslik başlıklarını gösterme" seçeneği: tüm derslikler filtrelenebilir tek tabloda.
- **Birim Bazlı Hoca Notları**:
  - Hoca notları, hocanın programlarını düzenleme yetkisine bağlandı (`ScheduleNotePolicy::canManageLecturerNotes`); birim seçildiğinde o birimdeki tüm hoca notları tek seferde getirilir.
- **Ana Sayfada Query Parametresiyle Program Yükleme**:
  - `unit_id`, `program_id`, `lecturer_id`, `classroom_id` gibi URL parametreleriyle program, hoca ve derslik programları otomatik yüklenir; eksik birim/bölüm hiyerarşisi otomatik tamamlanır.
- **JSON Dışa Aktarımında Staj Dersleri**:
  - JSON ders programı dışa aktarımına staj dersleri desteği ve UZEM/birim binası konum çözümlemesi eklendi.
- **UBS İçe Aktarmada CSV Desteği**:
  - `UbsLessonParser::loadSpreadsheet()` ile `.csv` dosyaları ayraç (virgül/noktalı virgül) otomatik algılanarak içe aktarılabilir.
- **Otomatik Asset Sürümleme**:
  - `AssetManager::versioned()` ile yerel JS/CSS URL'lerine dosya değiştirilme zamanına dayalı `?v=<mtime>` parametresi eklenerek tarayıcı önbelleği güncellemelerde otomatik geçersiz kılınır.
- **Mail Kuyruğu Log Erişimi**:
  - Mail kuyruğu yönetim kartına kuyruk loglarına doğrudan erişim butonu eklendi.

### Changed
- **Program Görüntüleme Yetkileri**:
  - Hoca ve üstü roller yayınlanmamış olanlar dahil tüm programları görüntüleyebilir.
  - Müsait gözetmen/derslik sorguları ve ders birleştirme yetkileri rol kontrolü yerine kapsam bazlı Policy kontrolüne (`SchedulePolicy::update`, `LessonPolicy::combine`) taşındı.
- **Program Yayınlama Bildirimleri**:
  - Program kartından yayınlama, toplu yayınlama ile aynı bildirim mantığına getirildi (`applyPublishStatus()`); farklı birim hocalarına da bilgilendirme maili gönderilir.
- **Excel Dışa Aktarım Altyapısı**:
  - Izgara ortak kodu `BaseGridExcelExporter`'a, başlık biçimlendirmesi `BaseExcelExporter::renderHeaderRow()`'a taşındı.
  - Liste hücreleri formül enjeksiyonuna karşı metin olarak yazılır; program adları virgül yerine alt alta yazılır.
  - Excel çıktıları ve arayüzdeki logolar yeni kurumsal logolarla (`gru_official_logo`) güncellendi.
- **Loglama**:
  - Veritabanına log yazımı kaldırıldı; loglama tamamen dosya tabanlı rotasyonlu JSON mimarisine geçti.
  - Servis loglarına `logContext()` ile işlemi yapan kullanıcı, sınıf/metod, URL ve IP bilgisi eklendi; ders loglarına ders kodu ve adı eklendi.
  - `DEBUG` kontrolü `isDebug()` helper'ı ile standartlaştırıldı, log seviyesi hiyerarşisi düzenlendi; yeni kurulumlarda varsayılan log seviyesi `INFO` oldu.
  - Mail kuyruğu boşken log kaydı oluşturulmaz.
- **Oturum Süresi**:
  - Uzun süren planlama işlemlerinde oturumun erken düşmemesi için oturum süresi 4 saate çıkarıldı.
- **Mobil Görünüm**:
  - Ders detay sayfası küçük ekranlarda tek sütun olarak gösterilir.

### Fixed
- **Misafir Erişimi ve Yayınlanmamış Programlar**:
  - Misafir kullanıcılar yalnızca yayınlanmış programları görür ve kayıt oluşturamaz; çok dönemli program kartlarına görüntüleme yetkisi kontrolü eklendi.
  - Misafir görünümünde tercih/uygun değil slotları gizlendi.
- **Sınav Birleştirme ve Mevcut Hesaplama**:
  - Alt dersi olan derslerin sınav birleştirmesinde oluşan "ID veya Tablo adı eksik" hatası giderildi.
  - Birleştirilmiş sınav mevcudu yalnızca bir alt dersi sayıyordu; tüm bağlı dersler hesaba katılıyor.
  - Sınav kartı ve düzenleme modalındaki mevcut, birleştirilmiş toplam mevcut olarak hesaplanıyor (`Lesson::getExamTotalSize`).
- **Gözetmen Seçimi**:
  - Başka satırda seçilen gözetmen artık diğer satırların listesinden kalıcı olarak çıkarılıyor.
- **Excel Çıktıları**:
  - Sınav ızgarasında kısa sınavların çıktıdan kaybolmasına yol açan rowspan hatası giderildi.
  - Tercih/uygun değil kayıtlarının ders ızgarası ve liste çıktılarına sızması engellendi.
  - Sınav liste çıktısında Derslik ve Hoca sütunlarının boş gelmesi ve program sütununda yanlış bağlı derslerin gösterilmesi düzeltildi.
  - Boş veya yetkisiz dışa aktarmada boş Excel yerine bilgilendirici hata verilir.
- **Bildirimler**:
  - `ScheduleMailer`'daki eksik import nedeniyle birime bağlı hocalara mail gitmemesi düzeltildi.
- **Ders Güncelleme**:
  - Hocanın kendi dersini güncellemesinde değişikliklerin kaydedilmemesi hatası giderildi.
- **Mail Kuyruğu Cron Komutu**:
  - Crontab komutunda FPM binary yolu yerine `/usr/bin/php` CLI yolu önceliklendirildi, çıktı yönlendirmesi düzenlendi.

### Removed
- `logs` tablosu, `DbLogHandler`, `LogRepository` ve `App\Models\Log` sınıfları.
- Kullanılmayan `bin/clean_empty_schedules.php` betiği (temizlik yayınlama sayfasında otomatik yapılıyor) ve `SchedulePublishService::bulkPublish()`.

## [0.3.4] - 2026-10-03

### Added
- **Monolog Kanal Bazlı Loglama Altyapısı ve Yönetim Paneli**:
  - `app`, `auth`, `schedule`, `security`, `database`, `queue`, `mail` ve `system` olmak üzere 8 bağımsız kanala sahip merkezi Monolog mimarisi (`Logger` servisi).
  - Günlük (`FILE_PER_DAY`), haftalık (`FILE_PER_WEEK = 'Y-\WW'`) ve aylık (`FILE_PER_MONTH`) rotasyon seçenekleri sunan özel `AppRotatingFileHandler` ve dinamik izin (`0666`) yönetimi.
  - JSON formatlı log kayıtlarını bellek dostu (memory-efficient) okuyan, filtreleyen ve sayfalayan `LogReaderService`.
  - AdminLTE uyumlu modern Log Yönetim Arayüzü (`/admin/logs`) ve ayarlar paneli (`/admin/settings#log`).
  - Arka plan kuyruk işleyicisinin (`bin/queue_runner.php`) Monolog `queue` kanalına bağlanması.
- **CSRF Belirteç (Token) Koruma Altyapısı**:
  - Durum değiştiren tüm POST, PUT, DELETE isteklerinde otomatik CSRF kontrolü sağlayan `CsrfMiddleware` ve istisna mekanizması (`#[WithoutCsrf]`).
  - Sayfadaki meta etiketinden CSRF token'ı dinamik okuyan, yenileyen ve tüm Fetch/XHR çağrılarına otomatik başlık ekleyen modüler `csrf.js` interceptor'ı.
- **UBS (Üniversite Bilgi Sistemi) Ders İçe Aktarma Modülü**:
  - Excel/CSV ve harici kaynaktan program bazında toplu ders aktarımı desteği, `LessonService` içinde atomik veritabanı transaction yönetimi (`BaseService::transaction`).
- **Hiyerarşik Sıralama ve Dinamik Seçim Arayüzü**:
  - Birim, bölüm, akademik unvan ve kıdem hiyerarşisine göre öğretim elemanı sıralama servisi (`sortLecturersHierarchically`) ve AdminLTE uyumlu dropdown render altyapısı (`renderLecturerSelectOptions`).
  - Ders atama (`/admin/assignlessons`) sayfasında dinamik, hiyerarşik ve hızlı seçim bileşenleri.
- **Program Değişiklik Bildirimlerinde Hoca Seçimi ve Detay Önizleme**:
  - Program güncellemelerinde bildirim gönderilecek hocaların filtrelenebilmesi ve değişiklik detaylarının önizleme modalında incelenebilmesi desteği.
- **Merkezi E-Posta Kuyruğu (MailQueue)**:
  - Tüm e-posta gönderimlerinin merkezi `MailQueue` üzerine yönlendirilmesi ve geliştirme/test ortamları için simülasyon loglarının iyileştirilmesi.
- **İmzalı Çerez (Signed Cookie) Güvenliği**:
  - Çerez kurcalama (cookie tampering) ve sahteciliği önlemek amacıyla HMAC-SHA256 tabanlı çerez imzalama ve doğrulama mekanizması (`Cookie::sign`, `Cookie::verify`).
- **Veritabanı Performans İndeksleri (v0.3.4)**:
  - `lesson_assignments` tablosunda `idx_la_lecturer_period` (`lecturer_id`, `academic_year`, `semester`).
  - `lessons` tablosunda `idx_lessons_program_semester` (`program_id`, `semester_no`).

### Changed
- **Katmanlı Mimari ve DRY Standartlaştırması (Refactoring Faz 1 - Faz 6)**:
  - **Controller Katmanı:** Tüm dosya yükleme doğrulama mantığı DRY prensibine uygun olarak taban `Controller::validateUploadedFile()` metoduna toplandı.
  - **Repository & Servis Katmanı:** Controller'lardaki doğrudan SQL sorguları ilgili repository sınıflarına devredildi; `SettingsService` katmanı soyutlanarak iş mantığı merkezileştirildi.
  - **Ders & Çakışma Yönetimi:** UBS ders aktarım ve senkronizasyon mantığı `LessonService` katmanına taşındı. Çakışma denetimleri `ConflictService` ve `ScheduleItemDTO` ile tip güvenli hale getirildi.
  - **Rol Ayrımı (UserRole Enum):** Akademik ve idari roller katı `UserRole` enum değerleriyle ayrıştırıldı, sorgularda negatif filtreleme yerine pozitif filtrelemeye geçildi.
  - **Kod Standartları:** Dosya içi inline namespace referansları temizlendi, tüm sınıflar için dosya başı `use` bildirimleri standartlaştırıldı.
  - **View-Controller Ayrımı:** View şablonlarındaki doğrudan model/controller bağımlılıkları temizlenerek veri akışı controller üzerinden `compact`/dizi olarak standartlaştırıldı.
  - **Modüler JavaScript:** `ajax.js` içerisinden CSRF yönetimi bağımsız `csrf.js` modülüne taşınarak sorumluluklar ayrıştırıldı.
- **İstemci Tarafı Bellek Yönetimi ve Yaşam Döngüsü Optimizasyonları (Frontend Faz 7)**:
  - `myHTMLElements.js` içerisinde Bootstrap Modal ve Toast öğelerinin `hidden.bs.modal` ve `hidden.bs.toast` event'lerinde otomatik DOM'dan kaldırılması ve `dispose()` çağrısıyla bellek sızıntılarının (memory leak) önlenmesi.
  - `ScheduleCard.js` içinde `AbortController` kullanılarak sticky table header kaydırma (scroll) ve yeniden boyutlandırma (resize) dinleyicilerinin temizlenmesi, `destroy()` yaşam döngüsü metodu.
  - `ExamScheduleCard.js` içerisinde sınav takvim haftası navigasyonuna mükerrer click olaylarını önleyen guard kontrolü.
  - `getSchedule.js` bileşeninde `matchMedia` dinleyicisinin düzgün kaldırılması ve eski Bootstrap Tooltip/Popover örneklerinin yok edilmesi.
- **Veritabanı N+1 Sorgu ve Toplu İşlem Optimizasyonu**:
  - Program notları ve ders atamalarında N+1 sorgular giderildi; `findByIds` ile toplu yükleme sağlandı.
  - Çoklu not okundu işaretleme işlemi döngüsel `UPDATE` yerine tekil `UPDATE ... WHERE id IN (...)` sorgusuna dönüştürüldü.
  - Birim bazında öğretim elemanı getirme sorgularındaki gereksiz aktif durum filtresi kaldırılarak tutarlılık sağlandı.

### Fixed
- **Form Otomatik Tamamlama (Autofill) Güvenlik ve Parola Düzeltmesi**:
  - Kullanıcı düzenleme ve profil formlarında tarayıcı parola yöneticilerinin yanlışlıkla veya fark edilmeden parola alanını doldurup şifre değiştirmesini engelleyen `autocomplete="new-password"` koruması eklendi.
- **XSS Açıkları ve Görünüm Güvenliği (Blade/View Sanitization)**:
  - 73'ten fazla view dosyasında potansiyel XSS açıklarına karşı `htmlspecialchars` ve güvenli `e()` global helper fonksiyonu uygulandı.
- **PHP 8.5 Uyumluluğu**:
  - PHP 8.5 deprecation ve warning uyarıları çözüldü, katı tip tanımları güncellendi.
- **Toplu İşlemlerde Rol Yetkilendirmesi**:
  - Toplu kullanıcı ve ders işlemlerinde yetkisiz rol atamalarını ve IDOR açıklarını engelleyen sıkı Policy kontrolleri entegre edildi.
- **Yarıyıl Seçimi Kaybı**:
  - Ders düzenleme ve ekleme sayfalarında yarıyıl (`semester_no`) seçiminin belirli koşullarda ezilmesi hatası giderildi.
- **Staj Dersleri Çakışma Düzeltmesi**:
  - Ders birleştirme esnasında staj derslerinin normal derslerle hatalı çakışma üretmesi engellendi.
- **Sekreter Rolü Kısıtlamaları**:
  - Sekreter rolü için yetki kısıtlamaları ve derslik sayısı hesaplama sorgusundaki hatalı sayımlar düzeltildi.
- **Profil Sayfası 500 Hatası**:
  - Eksik helper fonksiyon referansları giderilerek global helper altyapısı (`global_helpers.php`) standartlaştırıldı.

## [0.3.3] - 2026-09-26

### Added
- **Mobil Tek Gün Görünümü (Mobile Day View) Navigasyonu**:
  - Mobil cihazlarda (~768px altı) yatay kaydırma ihtiyacını ortadan kaldıran dinamik tek gün görünümü (`getSchedule.js`, `schedule.css`) eklendi.
  - Günler arası geçiş için şık gün hapları (pills), önceki/sonraki gün navigasyonu ve dokunmatik sağa/sola kaydırma (touch swipe) desteği entegre edildi.
  - Sınav haftası değişimlerinde aktif gün ve tarih etiketlerini senkronize eden `scheduleWeekChanged` event altyapısı kuruldu.
  - Tablo başlıklarına `data-day-index`, `data-day-name` ve `data-day-date` attribute'ları eklendi.
- **Ders Atama Modülü ve Toplu Dışa Aktarma (Assign Lessons)**:
  - Dönemsel ders atamaları için yeni **Ders Atama Sayfası (`/admin/assignlessons`)** geliştirildi: Program seçimi, dönem filtresi, satır içi (inline) öğretim elemanı/derslik/bina ataması, tekli ve toplu kaydetme yetenekleri sunuldu.
  - Program bazlı ders atama listesini Excel olarak indirebilme (`LessonAssignmentExcelExporter`) ve bölüm başkanı ile üst yetkililer için tüm programları tek bir Excel dosyasında ayrı sekmeler halinde toplu indirebilme (`exportMultiple`) özelliği eklendi.
  - 3+1 ve 7+1 staj uygulayan programlar için dönem bazlı otomatik yarıyıl öneri mekanizması (`getSuggestedSemesterNo`) geliştirildi.
- **Staj ve Mesleki Eğitim Dersleri Yönetimi Entegrasyonu (#42)**:
  - Staj ve mesleki uygulama dersleri için derslik seçimi ve derslik çakışma kontrolleri esnetilerek normal derslerle çakışma engeli kaldırıldı.
  - Program takvimlerinde ve Excel çıktılarında staj bilgileri tablonun altında açılır/kapanır (Bootstrap Collapse) özet kartı/tablosu olarak listelendi.
  - Dışa aktarma modalına staj tablosunu dahil etme seçeneği (`show_internship`) eklendi ve misafir kullanıcılardan gizlendi.
- **Büyük Sınıflar ve Amfiler İçin Çoklu Gözetmen Atama Desteği (#68)**:
  - Sınav programı düzenleme modalında (`ExamScheduleCard.js`) her bir derslik için birden fazla gözetmen seçebilme (TomSelect `multiple`) desteği eklendi.
  - Sınav takviminde tek bir salona birden fazla gözetmen atandığında derslik takviminde mükerrer kayıt oluşmasını engelleyen, her bir gözetmenin kendi kullanıcı programına bağımsız atama yansıtan servis altyapısı (`ExamScheduleService::saveExamScheduleItems`) kuruldu.
  - Çakışma kontrol servisi (`ConflictService` / `ConflictResolver`), sınav atamasındaki tüm derslik ve gözetmenleri otomatik olarak çakışma taramasına dahil edecek şekilde genişletildi.
  - Ders kartlarında (`_lessonCard.php`) ve sağ tık menüsünde (`showContextMenu`) bir derslikte görevli tüm gözetmenlerin isimleri ve programlarına hızlı erişim bağlantıları listelendi.
  - Excel (`ExamScheduleExcelExporter`) ve ICS takvim (`ExamScheduleIcsExporter`) dışa aktarma motorlarına çoklu gözetmen formatlaması entegre edildi.
  - Çoklu gözetmen atama, derslik tekilleştirme, sibling eşleme ve sınav silme işlemlerini doğrulayan kapsamlı entegrasyon testleri (`ExamScheduleServiceTest`) yazıldı.
- **Liste ve JSON Formatında Dışa Aktarma (Export)**:
  - Ders ve sınav programları için alternatif liste görünümünde Excel dışa aktarma servisi eklendi (`LessonScheduleListExcelExporter`, `ExamScheduleListExcelExporter`).
  - Harici entegrasyonlar için ders/sınav programı JSON dışa aktarma servisi (`LessonScheduleJsonExporter`, `ExamScheduleJsonExporter`) eklendi ve çıktılara bina (`building`) bilgisi dahil edildi.
- **KVKK ve Gizlilik Politikası Onay Mekanizması (#114)**:
  - Kullanıcıların KVKK Aydınlatma Metni ve Gizlilik Politikasını onaylamalarını zorunlu kılan `user_consents` tablosu, `UserConsent` modeli ve `UserConsentRepository` mimarisi kuruldu.
  - Oturum açan kullanıcılara ilk girişte bilgilendirme modalı, onay AJAX uçları ve yönetim/kamu footer alanlarına yasal metin bağlantıları eklendi.
- **Mutemet (payroll_officer) Rolü**:
  - Birim düzeyinde ders ve sınav programlarını görüntüleme ve dışa aktarma yetkilerine sahip yeni kurumsal rol tanımlandı.
- **Program Seçicide Dönem/Sınıf Filtresi (#115)**:
  - `_programSelector` bileşenine `semester_no` (dönem/sınıf) seçim kutusu eklendi; birim/bölüm/program bazında dinamik azami yarıyıl yükleme ve kaskad filtreleme sağlandı (`formEvents.js`).

### Changed
- **Gruplu Dersler, Derslik ve Hoca Görünümü UI/UX İyileştirmeleri**:
  - Ders adından grup bilgisi ayrılarak belirgin bir rozet (`.lesson-group-badge`) haline getirildi; `Lesson::getGroupLetter()` metodu eklendi ve grup kartları dikey sütun (column) düzenine geçirildi.
  - Derslik programlarında derslik adı yerine ait olduğu program bilgisi meta alanına taşındı; ders adlarının rahat sığması için 2 satıra kadar (`line-clamp: 2`) esneme desteği getirildi.
  - Hoca takvimlerinde ders kartı meta alanına program bilgisi eklendi.
- **Akademisyen Tekil Program Yayınlama Yetkisi**:
  - Öğretim elemanlarının kendi ders programlarını yayınlayabilmesi/yayından kaldırabilmesi için `SchedulePolicy` ve `SchedulePublishService` yetkilendirmesi sağlandı.
- **Dışa Aktarma Arayüzü**:
  - Dışa aktarma ekranında (`exportschedule.php`) butonlar alt satıra ve sağa hizalanarak daha ergonomik bir yerleşim sağlandı; `_programSelector` bileşenine `buttonPosition` desteği eklendi.
- **Çoklu Sahiplikte Güncelleme Tarihi Senkronizasyonu**:
  - Bir derste yapılan güncellemenin bağlı tüm sahip programlarının `updated_at` tarihini otomatik senkronize etmesi sağlandı.
- **Test ve Mailer İyileştirmeleri**:
  - Test ortamında sahte mail desteği, `ScheduleNoteDeletedEvent` bildirim testleri ve yeni modül testleri (staj, ders atama, mobil görünüm vb.) eklendi.

### Fixed
- **Ders Birleştirme ve Çoklu Schedule Bütünlüğü (#124, #100)**:
  - Program dışı (hoca, derslik, ders) schedule kayıtlarında `semester_no` değeri `NULL` yerine `0` olarak standartlaştırıldı ve veri tabanında deterministik tekillik sağlayan `uk_schedules_owner_period` UNIQUE indeksi oluşturuldu.
  - Ders birleştirmede program çakışma kontrolü (`checkProgramScheduleConflict`, `validateCombinationSlots`, `TimeHelper::isOverlapping`) eklendi; child ders birleştirilirken mükerrer hoca/derslik atamaları kaldırıldı.
  - Birleştirme kaynaklı bozuk serileştirilmiş veriler (fazladan süslü parantez) temizlendi.
  - `Lesson` modelinden veritabanında bulunmayan alanlar (`lecturer_id`, `semester`, `academic_year`) temizlendi; parçalı ders eklemelerinde child ders saati limit aşımı giderildi.
- **Grup Derslerinde Ardışık Saatlerin Otomatik Birleştirilmesi**:
  - Grup derslerinde ardışık saatlerin tek blokta birleştirilmesini sağlayan `mergeAdjacentItems` ve timeline veri normalizasyonu yapıldı.
- **ICS Takvim Çıktısında Grup Bilgisi ve Başlangıç Tarihi**:
  - ICS dışa aktarmalarında etkinlik başlığına `(Grup X)` ve açıklamaya grup detayı eklendi; `ExamType::startDateSettingKey` kullanılarak sınav/ders ICS çıktılarındaki yanlış başlangıç tarihi hatası giderildi.

## [0.3.2] - 2026-08-28

### Added
- **Veritabanı Tabanlı E-Posta Kuyruk (Mail Queue) Sistemi (#106)**:
  - Ders programı yayınlama ve görevlendirme bildirimlerinde toplu e-posta gönderimlerini arka planda asenkron işleyen `mail_queue` tablosu, `MailQueue` modeli, `MailQueueRepository` ve `MailQueueService` mimarisi kuruldu.
  - Sunucu crontab otomasyonu için CLI üzerinden çalışan `bin/queue_runner.php` ve parametrik batch kontrolü eklendi.
  - Eşzamanlı cron veya web isteklerinde mükerrer gönderimleri engelleyen atomik kilit (`atomicLockItem`) mekanizması geliştirildi.
- **E-Posta Kuyruğu & Crontab Yönetim Paneli**:
  - `/admin/mailqueue` yönetim sayfası eklenerek canlı istatistik kartları (Bekleyen, İşlenen, Başarılı, Hatalı), filtreli kuyruk tablosu, modal üzerinden hata detayları görüntüleme, tekil silme, toplu başarılı temizleme ve hatalıları yeniden deneme özellikleri sunuldu.
  - Sunucuya özel otomatik crontab komutu oluşturucu ve HTTP/HTTPS uyumlu güvenli panoya kopyalama aracı eklendi.
- **Kapsam Bazlı Hiyerarşik Program Yayınlama Sistemi**:
  - Program yayınlama süreci; Birim, Bölüm, Program, Derslik ve Öğretim Elemanı bazlı hiyerarşik kapsam filtresi ile yayınlama ve yayından kaldırma desteğine kavuşturuldu.
  - Yayınlama esnasında ilgili birim dışından görevlendirilen akademisyenlere otomatik çapraz görevlendirme e-posta bildirimi (`schedule_cross_unit_published`) gönderimi eklendi.
- **Boş Program Kayıtlarını Temizleme Altyapısı**:
  - İçerisinde hiçbir ders/sınav öğesi bulunmayan atıl `schedules` kayıtlarını tespit edip temizleyen servis ve `bin/clean_empty_schedules.php` CLI betiği eklendi; yayınlama ekranına temizleme seçeneği entegre edildi.
- **Birim Yöneticisi / Müdürü Desteği (#113)**:
  - `units` tablosuna `manager_id` alanı eklendi; birim ekleme/düzenleme formlarına birim yöneticisi/müdürü seçimi, unvan çoğul eki desteği ("Yardımcıları") ve admin dashboard'da müdür yardımcıları gösterimi sağlandı.
- **Dışa Aktarma (Export) Geliştirmeleri**:
  - Program kartlarına dönem bazlı hızlı dışa aktarma butonları eklendi. Excel ve ICS çıktılarında başlığa birim ve program adları dinamik olarak entegre edildi.
- **Public Portal & Karşılama Sayfası**:
  - Ziyaretçiler için modern, responsive public ana sayfa tasarımı ve optimize edilmiş logo/tab yapısı geliştirildi.

### Changed
- **DTO & Katı Tip Güvenliği Refactoring**:
  - Schedule dışa aktarma (`ScheduleExportFilterDTO`, `ScheduleExportOptionsDTO`) ve tüm DTO katmanında `readonly class` ve katı tip (`strict types`) standardına geçildi.
- **Rol ve Yetkilendirme Standardizasyonu**:
  - Rol kontrolleri `Gate::hasRole()` ve `Gate::authorizeRole()` ile merkezi hale getirildi.
- **Katmanlı Mimari ve Temiz Kod (Clean Code)**:
  - `MailQueueRepository` katmanı projeye kazandırıldı; veri erişim mantığı servislerden repository'ye devredildi.
  - Proje genelindeki tüm PHP dosyalarında yer alan kullanılmayan `use` import tanımlamaları ve satır içi (inline) namespace referansları temizlendi.
- **Test ve Geliştirici Ortamı İyileştirmeleri**:
  - Test ortamında veritabanı ayarlarının `App/.env` üzerinden izole okunması için PHPUnit bootstrap altyapısı kuruldu; test sırasında veritabanı log kirliliği kapatıldı.

### Fixed
- **Bina ve Derslik Benzersizlik (UNIQUE) Kısıtlamaları (#105)**:
  - `buildings` tablosunda isim kısıtlaması `(unit_id, name)`, `classrooms` tablosunda ise `(building_id, name)` bileşik anahtarına dönüştürülerek farklı birim/binalarda aynı isimli derslik ve bina oluşturulabilmesi sağlandı.
- **Bölüm Başkanı ve Bina Seçimi (#108, #109)**:
  - Bölüm başkanı seçiminde kadrosu farklı birimde olup ilgili bölüme bağlı tüm hocaların listelenmesi sağlandı. Binasız birimlerde ders eklerken tüm binaların seçilebilmesi özelliği getirildi.
- **Gereksiz Schedule Oluşumu**:
  - Kullanıcı profil ve müsaitlik sayfalarında sorgulama yaparken veritabanında boş `schedules` kaydı oluşması engellendi.
- **Misafir Dışa Aktarma & CSS Düzeltmeleri**:
  - Oturum açmamış ziyaretçilerin yayınlanmış programları Excel/ICS olarak indirebilmesi sağlandı. Dark mode, hücre genişlikleri ve AdminLTE `.card-tools` kart başlığı hizalama sorunları giderildi.
- **Simülasyon / Canlı E-Posta Modu**:
  - `mail_driver` ayarının SMTP canlı moduna geçişi ve ayarlar sayfasından yapılandırılması güvenceye alındı.

## [0.3.1] - 2026-08-23

### Added
- **Ders Programı Yayım & Bildirim**: Yayına alınan ders programları için e-posta bildirim sistemi (Excel/ICS ekleri ve HTML tablo desteğiyle) eklendi.
- **Toplu Yayınlama**: Ders programlarında toplu yayınlama ve yayından kaldırma özellikleri geliştirildi.
- **Entegre Düzenleme**: Hoca ve program bazlı ders programı düzenleme entegrasyonu tamamlandı.
- **Loglama Geliştirmeleri**: Loglama mimarisi iyileştirildi, schedule logları sadeleştirildi ve arayüz kodları ayrıştırıldı; Admin dashboard'a son loglar için "Tümünü Gör" butonu eklendi.
- **Filtreleme & Arayüz**: Anasayfada GET parametreleri ile otomatik birim, bölüm ve program seçimi eklendi.
- **Kısıtlamalar & Görsellik**: Program tablosunda bağlı (child) derslerin program çakışmaları vurgulandı; preferred ve unavailable statüsündeki öğelerin Excel ve ICS çıktılarından filtrelenmesi sağlandı.
- **Birim Türleri**: "Rektörlük" birim türü sisteme tanımlandı.
- **Bildirim & Durumlar**: `ScheduleNote` tablolarına "Bilgi Verildi" durumu eklendi.

### Changed
- **Gelişmiş Yetkilendirme & Çapraz Bağlantılar (Cross-Departmental)**: Akademisyenlerin farklı birimlerdeki dersleri için `UserAffiliation` (Görevlendirme/Bağ) altyapısı kuruldu; SchedulePolicy ve ScheduleController'a yetkilendirilmeyen bölümlerin derslerini arayüz manipülasyonu ile değiştirmeyi engelleyen ders bazlı kilit mekanizması getirildi.
- **Vanilla JS Dönüşümü**: jQuery kullanımı projeden tamamen kaldırılarak DataTables vanilla javascript yapısına aktarıldı.
- **Arayüz (UI/UX) İyileştirmeleri**: Program tablosu sütun ve kart genişliklerine max 450px sınırı eklendi; ders içe aktarma işlemlerinde mevcudu başlığı "Kontenjan/Mevcut" olarak değiştirildi; Bina ismi güncellendi.
- **Kod Mimarisi & Refactoring**: Ders saati, ders sayısı, haftalık ders saati hesaplama mantıkları Controller'dan alınıp `User` modeli ve `UserRepository` içerisine taşındı. Birim/Bölüm/Program seçim filtreleri `_programSelector` bileşenine aktarıldı.

### Fixed
- **Yetkilendirme Hataları**: Ders birleştirme yetki kontrolleri, bina detay sayfasındaki bağlı birim ilişkisi, kurumsal yapı listeleme ve ithal (import) yetkilendirme filtreleri sıkılaştırıldı ve düzeltildi. `ScheduleNotePolicy::canManageNotes` kaskad yetki kontrolü onarıldı.
- **Sürükle-Bırak & Kilitler**: `toggleLockScheduleItems` işlemi, gruba bağlı diğer sibling öğeleri de kilitleyecek şekilde güncellendi.
- **Bölüm/Program Eşsizlik Kısıtlamaları**: Bölüm ve program `unique` kısıtlamaları (constraint) birim ve bölüm bazlı olarak güncellenerek mükerrer kayıtlardan kaynaklı hatalar önlendi.
- **Derslik Çakışmaları**: Gruplu derslerde derslik isminin okunmaması sorunu ve derslik çakışmasındaki ders saati mantık hatası düzeltildi.
- **Görünürlük (Visibility)**: Yetkisiz kullanıcıların yayınlanmamış programları görüntüleme hatası düzeltildi; bölüm başkanları ve yöneticiler için unpublished (yayınlanmamış) program görünürlükleri sağlandı.
- **E-posta & Arayüz Düzeltmeleri**: Program istek durum e-posta şablonu metinleri ve rozet/ikon renkleri düzeltildi. Akademisyen panosunda kişisel ders programı yükleme hatası giderildi.
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
