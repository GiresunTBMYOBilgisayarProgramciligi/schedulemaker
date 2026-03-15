# Test Uygulama Planı

Bu doküman, "Ders Programı Düzenleme Sistemi" için test altyapısının kurulması ve sürdürülebilir bir test stratejisinin uygulanması için yol haritasını içerir.

## 1. Test Stratejisi

Proje için üç katmanlı bir test yaklaşımı benimsenecektir:

- **Unit Tests (Birim Testler)**: Harici bağımlılığı olmayan (DB, Dosya Sistemi vb.) yardımcı sınıfların ve iş mantığının test edilmesi.
- **Integration Tests (Entegrasyon Testleri)**: Birden fazla bileşenin (Servis + Repository + Database) birlikte çalışmasının test edilmesi.

## 2. Test Ortamı ve Araçlar

- **Test Framework**: PHPUnit 10+
- **Database**: 
    - Testler için ayrı bir MySQL veritabanı (`schedulemaker_test`) veya SQLite (InMemory) kullanılacaktır.
    - Her test öncesinde veritabanı "Clean State" (temiz durum) haline getirilecektir.
- **Configuration**: `.env.test` dosyası aracılığıyla test ortamı ayarları yönetilecektir.

## 3. Öncelikli Test Alanları

### 3.1. Yardımcı Sınıflar (Unit Tests)
- `App\Helpers\TimeHelper`: Zaman hesaplamaları, aşım kontrolleri, çakışma mantığı.
- `App\Services\TimelineService`: Zaman çizelgesi düzleştirme (flattening), dilim birleştirme.
- `App\Validators\ScheduleItemValidator`: Veri giriş validasyonları.

### 3.2. Servis Katmanı (Integration Tests)
- `App\Services\ScheduleService`:
    - `saveScheduleItems`: Başarılı kayıt, çakışma durumunda hata yönetimi.
    - `processItemDeletion`: Parçalı silme ve zaman kayması kontrolleri.
    - `mergeGroupItems`: Gruplu derslerin doğru birleştirilmesi.
- `App\Services\AvailabilityService`: Müsait hoca ve derslik sorgularının doğruluğu.
- `App\Services\ConflictService`: Karmaşık çakışma kurallarının (Grup dersi, hoca çakışması vb.) kontrolü.

## 4. Uygulama Adımları

1. **Hazırlık**:
    - `composer require --dev phpunit/phpunit` komutu ile PHPUnit kurulur.
    - `phpunit.xml` konfigürasyon dosyası oluşturulur.
    - `tests/` dizini altında `Unit` ve `Integration` klasörleri oluşturulur.

2. **Temel Testlerin Yazılması**:
    - İlk olarak `TimeHelperTest.php` yazılarak altyapı doğrulanır.
    - BaseTestCase oluşturularak DB transaction yönetimi ve setup/teardown işlemleri standardize edilir.

3. **Otomatikleştirme**:
    - Git push öncesi testlerin çalışması sağlanır (İsteğe bağlı).

## 6. İleri Seviye Test Teknikleri ve Varyasyonlar

Testlerin kalitesini ve kapsama alanını artırmak için aşağıdaki yaklaşımlar uygulanmalıdır:

### 6.1. Data Providers (Veri Sağlayıcılar)
Aynı mantığı farklı veri setleriyle test etmek için kullanılır.
- **Kullanım**: Bir metodun farklı girdi kombinasyonları (doğru format, yanlış format, sınır değerler) için nasıl davrandığını tek bir test metoduyla kontrol edin.
- **Örnek**: `TimeHelper::isOverlapping` için çakışan, çakışmayan, ucu ucuna değen tüm senaryoları bir dizi içinde tanımlayın.

### 6.2. Sınır Değer Analizi (Edge Cases)
Hataların en sık görüldüğü uç noktaları hedefleyin:
- **Zaman**: Günün başlangıcı (00:00) ve bitişi (23:59).
- **Miktar**: 0 saatlik ders, hoca sınırı tam dolmuş hoca, boş sınıf mevcudu.
- **Veri Tipleri**: Beklenen dizi yerine null veya boş string gelmesi durumu.

### 6.3. Negatif Testler
Sistemin sadece doğru veriyi işlemesi değil, yanlış veriyi de güvenli bir şekilde reddetmesi gerekir:
- Geçersiz bir email ile kullanıcı kaydı denemesi.
- Bitiş saati başlangıç saatinden önce olan bir ders öğesi kaydı.
- Beklenen hata fırlatıldığının (`expectException`) doğrulanması.

### 6.4. Entegrasyon Varyasyonları (Senaryo Bazlı)
Gerçek dünya senaryolarını simüle edin:
- **Çakışma Senaryosu**: Aynı hocaya aynı saatte iki farklı programda ders atanması durumunda sistemin engelleme yapması.
- **Kapasite Senaryosu**: Laboratuvar kapasitesinin üzerinde öğrenci içeren bir dersin atanması durumunda uyarı verilmesi.
- **Transaction Testi**: Çoklu kayıt sırasında bir adımda hata oluştuğunda, o ana kadar yapılmış tüm veritabanı işlemlerinin geri alınması (Rollback).

### 6.5. Mocking (Taklit Etme)
Ağır veya dışa bağımlı işlemler (E-posta gönderimi, dış API çağrıları) `createMock()` kullanılarak taklit edilmeli, böylece testler hızlı ve izole kalmalıdır.
